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
