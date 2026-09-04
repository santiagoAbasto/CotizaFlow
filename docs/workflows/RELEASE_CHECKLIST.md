# Release Checklist

## Calidad

- [ ] Tests Pest relevantes ejecutados y verdes.
- [ ] Tests Vitest relevantes ejecutados y verdes.
- [ ] Flujos Playwright fundamentales/relevantes ejecutados y verdes.
- [ ] Build frontend de producción exitoso.
- [ ] Sin errores ni warnings inesperados en consola frontend.
- [ ] Diff revisado; no se eliminaron ni debilitaron tests.

## Datos y seguridad

- [ ] Migrations revisadas en `up` y estrategia de rollback/forward fix.
- [ ] Seguridad de backup y migración confirmada para cambios de datos.
- [ ] Tenant isolation probado en lectura, escritura, búsquedas, exports, APIs y archivos afectados.
- [ ] Policies, roles y permissions verificados en backend.
- [ ] Validación, uploads, rate limits y auditoría revisados cuando aplican.
- [ ] No hay secretos, tokens, credenciales ni datos sensibles en código, artefactos o logs.
- [ ] Variables de environment documentadas y disponibles por entorno, sin valores secretos en el repositorio.

## Operación

- [ ] Queues configuradas; retries, timeouts y failed jobs revisados.
- [ ] Scheduler configurado y comandos idempotentes cuando corresponde.
- [ ] Webhooks con firma, idempotencia, replay protection y observabilidad verificadas.
- [ ] Logs, métricas y alertas suficientes para detectar fallos.
- [ ] Integraciones externas probadas con errores y timeouts.
- [ ] Plan de despliegue, rollback/forward fix y responsables definidos.

Registrar comandos ejecutados, resultados, excepciones aceptadas y riesgos pendientes antes de liberar.
