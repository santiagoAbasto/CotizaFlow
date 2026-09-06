# 0001 — Resolución del contexto de tenant

- **Estado:** accepted (amended 2026-09-04)
- **Fecha:** 2026-09-04
- **Enmienda:** la decisión original ligaba cada usuario a una company (`users.company_id`). Esa parte queda **superseded** por la sección "Enmienda" al final de este documento. El resto de la decisión sigue vigente.
- **Relacionado:** [tenancy](../architecture/TENANCY.md), [seguridad](../architecture/SECURITY.md)

## Contexto y problema

`docs/architecture/TENANCY.md` establece que un usuario de una empresa nunca puede leer, modificar, buscar, exportar ni descargar datos de otra, y prohíbe confiar en `company_id` enviado por el frontend. Al iniciar el código hay que decidir cómo se resuelve y se aplica ese límite, porque cada módulo posterior hereda la decisión.

## Opciones consideradas

1. **Verificación por consulta.** Buscar y luego comprobar la pertenencia. Rechazada: el documento de tenancy prohíbe explícitamente "búsquedas globales seguidas de comprobaciones tardías", y depende de que nadie olvide la comprobación.
2. **Base de datos por tenant.** Aislamiento más fuerte, pero coste operativo alto (migraciones, backups, conexiones) desproporcionado para conseguir los primeros 3 clientes.
3. **Base compartida con `company_id` y global scope que falla cerrado.** Elegida.

## Decisión

- `TenantContext` es un servicio **scoped** que guarda el `company_id` activo. Es scoped y no singleton para que un worker de colas no arrastre el límite de un job al siguiente.
- El contexto se puebla solo desde estado verificado por backend: el middleware `ResolveTenantContext` lo toma de `$request->user()`, o un bloque explícito `runAs()` para jobs, comandos y seeders. Nunca desde input del request.
- `BelongsToTenant` aplica `TenantScope` en tiempo de construcción de la query, de modo que una fila ajena nunca llega a memoria.
- El scope **falla cerrado**: sin contexto lanza `TenantContextMissing` en vez de degradar a una consulta sin filtrar.
- `company_id` es inmutable tras la escritura y queda fuera de `$fillable`; solo lo estampa el backend.
- `User`, `Company` y `Membership` no llevan el trait: `Company` define el límite y `User`/`Membership` son su origen, ya que la autenticación resuelve un usuario antes de que exista contexto.
- ~~El email de usuario es único **por empresa**, no global.~~ **Superseded — ver Enmienda.**

## Consecuencias y trade-offs

- Todo acceso a datos fuera de un request autenticado debe declarar su tenant con `runAs()`. Es deliberadamente incómodo: hace visible el límite.
- Un scope global es una red de seguridad, no una autorización. Sigue siendo obligatorio añadir Policies por operación sensible.
- La base compartida implica que un fallo en el scope expone datos de otros tenants; por eso los tests de aislamiento son obligatorios por módulo.

## Enmienda (2026-09-04) — identidad global y membresías

### Qué se reemplaza

La decisión original modelaba la pertenencia como `users.company_id`, con email único por empresa. Se reemplaza por identidad global más membresías explícitas.

### Por qué

`users.company_id` obliga a duplicar la persona por cada empresa. Eso rompe tres cosas a la vez:

1. **Autenticación ambigua.** Con email único por empresa, dos filas comparten email y el login no puede resolver a quién autenticar sin pedir además la empresa. El password reset hereda la misma ambigüedad.
2. **Un usuario, una empresa.** El ICP incluye contadores, consultores y dueños con más de una razón social. El modelo anterior lo impedía por construcción.
3. **Rol acoplado a la identidad.** `users.role` no puede expresar que la misma persona sea `owner` en una empresa y `sales` en otra.

### Nueva estructura

- `users` — identidad global: `id`, `name`, `email` **único global**, `password`.
- `companies` — el tenant.
- `memberships` — `user_id`, `company_id`, `role`, `status`, con `unique(user_id, company_id)`.

Se eligió `memberships` sobre `company_user` porque la tabla lleva estado (`status`), rol y comportamiento de dominio (`grantsAccess()`): es una entidad, no una tabla pivote anónima.

`role` sale de `User` y pasa a `MembershipRole`; se añade `MembershipStatus` (`invited`, `active`, `suspended`), y solo `active` concede acceso.

### Selección de empresa activa

Se separa explícitamente la **primitiva confiable** (`TenantContext::enter()`, que no autoriza) de la **selección autorizada** (`ActivateCompanyForUser`, que verifica membership y status). Un `company_id` de origen no confiable solo puede entrar por la segunda.

En web, la empresa activa vive en sesión y se re-autoriza contra la membership viva en cada request, de modo que revocar un acceso surte efecto de inmediato y no cuando la sesión expira.

**La selección nunca es automática.** Una primera versión elegía una empresa por defecto cuando la sesión no tenía ninguna válida; eso implicaba que un `POST` dirigido a la empresa B, cuya membership había sido revocada, terminaba ejecutándose dentro de la empresa A. El request se rechaza ahora con `403 NO_ACTIVE_MEMBERSHIP`, `409 ACTIVE_COMPANY_REQUIRED` o `409 ACTIVE_MEMBERSHIP_INVALID` según el caso. Ver [tenancy](../architecture/TENANCY.md).

### Consecuencias

- Registro y login deberán crear `User` + `Company` + `Membership(owner, active)` en una sola transacción.
- Cambiar de empresa activa es una operación de dominio con autorización propia, no una preferencia de UI.
- `Membership` no es tenant-scoped: su consulta es lo que **establece** el límite, así que toda lectura debe acotar `company_id` explícitamente.
- Toda Policy de `Membership` es tenant-bound: administrar la empresa B exige tenerla activa, aunque el actor también pertenezca a ella. Pertenecer no alcanza; hay que haber cambiado de empresa explícitamente.
- El rol `owner` está protegido: un `admin` no puede modificar, suspender, degradar ni eliminar una membership de owner, ni promover a nadie a owner; y el último owner activo no puede ser degradado, suspendido ni eliminado por nadie.
- Las transiciones de role/status siempre incluyen el valor destino y se ejecutan mediante Actions transaccionales. Ambas Actions bloquean primero la fila de `companies` y luego sus memberships, en un orden único, para serializar cambios concurrentes que podrían eliminar al último owner activo.
