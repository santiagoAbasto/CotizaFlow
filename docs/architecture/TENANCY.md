# Multi-tenancy

## Invariante principal

Toda entidad comercial pertenece explícita o inequívocamente a un tenant/company. Un usuario de una empresa nunca puede leer, modificar, buscar, exportar ni descargar datos o archivos de otra.

## Company es el único tenant

`Company` es el tenant. Una futura `Branch` es una subdivisión organizativa **dentro** de una company (`Company hasMany Branch`) y **nunca** es un tenant: no define un límite de aislamiento, no aparece en `TenantContext` y no participa en el global scope. Filtrar por sucursal es una decisión de negocio; filtrar por company es una invariante de seguridad. No confundirlas.

## Identidad y membresías

`User` es una identidad global con email único a nivel de sistema, lo que mantiene login y password reset sin ambigüedad. Un usuario no pertenece a una company: `Membership` lo coloca dentro de una, con su propio `role` y `status`. La misma persona puede pertenecer a varias companies con un rol distinto en cada una. El rol nunca vive en `User`. Ver [ADR 0001](../adr/0001-tenant-context-resolution.md).

## Selección de empresa activa

Un `company_id` recibido del cliente es siempre input no confiable. La única ruta autorizada es `ActivateCompanyForUser`, que busca la membership del usuario autenticado, verifica que su status conceda acceso y recién entonces entra al contexto. `TenantContext::enter()` es una primitiva confiable que no autoriza nada: quien la llama afirma que el id ya fue verificado.

Ausencia de membership y membership ajena producen el mismo mensaje de rechazo, para que la respuesta no permita enumerar tenants ajenos.

### Sin fallback silencioso

Si la empresa activa de la sesión deja de ser utilizable —membership revocada o suspendida— el sistema **no** elige otra automáticamente. Hacerlo significaba que un `POST` dirigido a la empresa B terminaba ejecutándose dentro de la empresa A sin que nadie lo notara.

La selección obsoleta se limpia y el request se rechaza con un código de dominio estable:

| Situación | Status | Código |
|---|---|---|
| Usuario sin ninguna membership activa | `403` | `NO_ACTIVE_MEMBERSHIP` |
| Hay empresas, pero ninguna seleccionada | `409` | `ACTIVE_COMPANY_REQUIRED` |
| La empresa seleccionada dejó de ser válida | `409` | `ACTIVE_MEMBERSHIP_INVALID` |

Un usuario sin membership recibe `403` y no `409` porque no hay nada que pudiera seleccionar: pedirle que elija sería un callejón sin salida. En ningún caso se llega a un `500`.

Elegir empresa es una acción explícita del usuario. Las rutas de selección y onboarding se marcan con el prefijo de nombre `tenancy.` y quedan exentas; continúan **sin tenant atado**, de modo que cualquier consulta tenant-scoped que intenten sigue fallando cerrado.

## Semántica de `runWithoutTenant()`

`runWithoutTenant()` ejecuta un callback **sin tenant activo**. No es un bypass cross-tenant: el global scope sigue instalado y toda consulta tenant-scoped dentro del callback falla cerrado con `TenantContextMissing`.

No existe una API genérica de bypass. La única operación que cruza el límite es `TenantOwnershipTransfer`.

## Orden de middleware

`ResolveTenantContext` debe ejecutarse **después** de la autenticación y **antes** de `SubstituteBindings`. El route model binding consulta modelos tenant-scoped: si el contexto todavía no está atado, esa consulta corre sin límite. La prioridad está fijada explícitamente en `bootstrap/app.php`.

Toda ruta comercial tenant-scoped debe declarar autenticación. Como defensa adicional, si una ruta olvidara hacerlo y un guest alcanzara un parámetro tipado con un modelo tenant-owned, `ResolveTenantContext` responde `401` antes de `SubstituteBindings`; nunca expone `TenantContextMissing` como un `500`. La excepción interna sigue fallando fuerte para código autenticado, jobs y comandos donde su aparición representa un bug.

## Inmutabilidad del tenant

El `company_id` de una entidad comercial es inmutable por las vías normales de escritura. Los eventos de modelo de Eloquent **no** se disparan en escrituras del query builder, así que la protección se aplica en dos capas: `TenantBuilder` y el `TenantQueryBuilder` subyacente. La segunda es imprescindible porque Eloquent expone `toBase()`, `getQuery()` y forwarded calls.

- `update()` — rechaza `company_id` como columna.
- `insert()` / `insertOrIgnore()` / `insertGetId()` — si la fila omite `company_id` lo estampa desde el contexto; si nombra el tenant activo la acepta; si nombra otro, la rechaza.
- `upsert()` — valida tanto las columnas de update como el `company_id` de cada fila insertada.
- Operaciones aritméticas (`increment`, `decrement` y variantes `Each`) — rechazan la columna tenant.
- `updateOrInsert()` — exige contexto, incluye el tenant en la búsqueda y nunca acepta ownership en los valores actualizables.
- `delete()` desde el builder base — vuelve a aplicar el tenant antes de borrar.
- `insertUsing` y `truncate` — se rechazan porque no pueden preservar o estampar ownership de forma verificable.

Los nombres de columna se normalizan en `ColumnName` (minúsculas, sin comillas, sin calificador de tabla), de modo que `company_id`, `COMPANY_ID`, `customers.company_id` y `` `customers`.`company_id` `` se detectan por igual. La comparación por igualdad exacta contra una sola grafía fue precisamente por donde entraron los bypasses.

La validación es **previa a la escritura**: un lote con una sola fila inválida no escribe nada.

`TenantBuilder` **no tiene bandera de bypass**. La única vía soportada para mover un registro entre tenants es `TenantOwnershipTransfer`, que emite su escritura sobre el query builder base —sin pasar por Eloquent— dentro de una transacción, y exige un `TransferAuthorization` con responsable y motivo explícitos. Nada enlaza ese objeto desde input HTTP, así que la operación no es alcanzable desde un request.

El límite de confianza termina en la API del modelo. Código que llama deliberadamente `DB::table()` abandona Eloquent y es infraestructura privilegiada; no se promete interceptarlo. Fuera de migraciones y `TenantOwnershipTransfer`, ese escape requiere revisión de seguridad explícita.

`Membership` establece el límite y por eso no usa global scope. Sus cambios de `role` y `status` pasan obligatoriamente por `ChangeMembershipRole` y `ChangeMembershipStatus`. Las Actions reciben el valor destino, abren una transacción, bloquean primero la company y después sus memberships, autorizan sobre estado fresco y recién entonces escriben. El orden común de locks serializa transiciones concurrentes y evita que dos owners puedan observar simultáneamente que "queda otro".

**Límite actual:** la transferencia registra un `Log::warning` estructurado. Eso es trazabilidad, no un audit log inmutable; cuando exista infraestructura de auditoría debe migrarse a ella.

## Trabajo en segundo plano

**Nunca serializar modelos tenant-scoped como payload de jobs, queued listeners, imports o exports.** Laravel rehidrata un modelo encolado mediante una consulta nueva, ejecutada con el límite que el worker tenga en ese momento — exactamente la situación que el scoping existe para impedir.

El payload es: `company_id` + ids escalares. El flujo es: el job arranca → `TenantJobMiddleware` abre el límite → se consultan las entidades dentro del contexto → se ejecuta → el contexto se restaura. Un worker es de larga vida y procesa jobs de muchos tenants en secuencia: restaurar el contexto es lo que impide que el límite de un job se filtre al siguiente.

Antes de abrir el límite, `TenantJobMiddleware` verifica que la company **siga existiendo**. Un job cuyo tenant fue eliminado entre el dispatch y la ejecución falla de forma explícita en lugar de correr bajo un tenant fantasma, donde toda consulta devolvería vacío y el trabajo parecería haber tenido éxito.

La convención está sostenida por un test (`tests/Feature/Architecture/QueuedPayloadConventionTest.php`) que detecta un modelo tenant-scoped pasado directamente, una colección de modelos declarada en el docblock, y un nivel de contención en DTO.

**Es un guardrail, no una garantía.** No ve un modelo asignado a una propiedad pública después de construir, escondido en un array sin tipar, ni envuelto a más de un nivel. La regla se sostiene en el diseño y el review; el test solo abarata las regresiones frecuentes.

## Web, API, jobs y comandos

La resolución de tenant **no** está atada al grupo `web`. `TenantContext` es la primitiva compartida; lo que cambia es únicamente cómo se descubre el tenant en cada superficie:

- **web** — `ResolveTenantContext` desde la sesión, re-autorizada contra la membership viva en cada request.
- **API** — cuando exista, el mismo `ActivateCompanyForUser` a partir del token/credencial; el contexto y el scope no cambian.
- **jobs / listeners** — `InteractsWithTenant` + `TenantJobMiddleware`.
- **comandos / seeders** — `TenantContext::runAs()` explícito.

Ninguna superficie puede saltarse el global scope: sin contexto atado, las consultas fallan cerrado.

## Reglas de implementación

- Resolver el tenant desde contexto autenticado y validado por backend.
- Nunca confiar en `company_id`, `tenant_id` ni IDs relacionados enviados por frontend.
- Aplicar scoping en queries y relaciones, además de autorización mediante Policies.
- Validar que entidades relacionadas pertenezcan al mismo tenant antes de asociarlas.
- Evitar búsquedas globales seguidas de comprobaciones tardías.
- Aplicar aislamiento también a jobs, exports, imports, APIs, webhooks, cache, archivos y generación de PDFs.
- Incorporar tenant en cache keys, rate limits, rutas de storage y claves de idempotencia cuando corresponda.
- Registrar acciones sensibles con actor, tenant, recurso y resultado.

## Tests obligatorios

Cada módulo comercial debe cubrir intentos de:

- lectura cruzada;
- modificación y eliminación cruzadas;
- búsqueda cruzada;
- exportación cruzada;
- acceso cruzado mediante API;
- acceso cruzado a archivos.

Añadir casos para IDs manipulados, relaciones de otro tenant, ejecución asíncrona, usuarios sin permisos y recursos inexistentes. Las respuestas no deben filtrar información útil para enumerar recursos ajenos.
