# Seguridad

La seguridad existe en backend y por capas; ocultar acciones en UI nunca sustituye autorización.

## Controles mínimos

- Aislamiento por tenant en datos, búsquedas, cache, jobs, exports y archivos.
- Autorización mediante Policies y permisos en cada operación sensible.
- Validación con Form Requests y normalización explícita de entradas.
- Protección CSRF para sesiones y configuración segura de cookies.
- Escape y sanitización contextual frente a XSS; contenido generado por IA también es no confiable.
- ORM/query binding frente a SQL injection; evitar SQL construido con input.
- Uploads con tamaño máximo, allowlist de tipos, verificación MIME/contenido, nombres generados y bloqueo de extensiones ejecutables.
- Throttling y rate limits por actor, tenant, IP o credencial según el riesgo.
- Webhooks con firma, timestamp, secreto seguro, idempotencia y replay protection cuando corresponda.
- Audit logs inmutables para cambios sensibles y trazabilidad de actor, tenant y recurso.
- Secretos únicamente en environment o secret manager; nunca en código, fixtures, logs ni commits.
- Logging seguro con redacción de tokens, credenciales, PII y payloads sensibles.

## Revisión por feature

Definir actor, permiso, tenant, entradas, datos sensibles, límites, efecto financiero, idempotencia y auditoría. Probar abuso, manipulación de IDs, relaciones cruzadas, cargas maliciosas, replay y condiciones de carrera según aplique.

Los errores deben ser útiles operativamente sin exponer stack traces, existencia de recursos ajenos, secretos o detalles internos.

## Seguridad de la base de datos de tests

La suite ejecuta `migrate:fresh`, que **borra todas las tablas** de la conexión activa. La protección es en capas:

1. `phpunit.xml` declara `APP_ENV`, `DB_CONNECTION` y `DB_DATABASE` con `force="true"` como configuración esperada de la suite. No se considera una frontera suficiente: según cómo se inicie el proceso, variables exportadas o un config cache todavía pueden determinar la configuración efectiva.
2. La garantía real es `App\Testing\TestDatabaseGuard`: valida **la configuración efectiva que Laravel va a usar**, no las variables declaradas o esperadas, y aborta antes de cualquier operación destructiva. La distinción es la que importa: un proceso puede declarar `cotizaflow_testing` mientras Laravel resuelve la conexión a `cotizaflow`. Una guardia que solo mirara el entorno aprobaría borrar la base de desarrollo.

La guardia corre desde `TestCase::refreshApplication()`, que en el ciclo de Laravel ocurre después de crear la aplicación —cuando la configuración efectiva ya existe— y antes de `setUpTraits()`, que es donde `RefreshDatabase` ejecuta `migrate:fresh`.

Aborta si:

- hay **config cache activo** durante los tests;
- el entorno efectivo no es `testing`;
- la conexión efectiva no es `mysql`;
- la base efectiva está vacía, es una base no-testing, o no está en la allow-list;
- hay una URL de base efectiva que sobreescribiría el nombre.

Un config cache se **rechaza en vez de interpretarse**. Razonar sobre un cache construido desde un `.env` desconocido es estrictamente peor que negarse a correr. El mensaje es `TEST DATABASE SAFETY ABORTED:` seguido del motivo, y nunca imprime credenciales.

La única base que la suite puede destruir es `cotizaflow_testing`.

**Recomendación operativa (no implementada en el repo):** crear un usuario MySQL dedicado a tests con permisos **únicamente** sobre `cotizaflow_testing`, de forma que un fallo de configuración no tenga siquiera privilegio para tocar la base de desarrollo. Es defensa a nivel de motor, complementaria a la de aplicación. No se versionan credenciales: cada entorno define las suyas fuera del repositorio.
