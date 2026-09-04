# Multi-tenancy

## Invariante principal

Toda entidad comercial pertenece explícita o inequívocamente a un tenant/company. Un usuario de una empresa nunca puede leer, modificar, buscar, exportar ni descargar datos o archivos de otra.

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
