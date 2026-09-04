# Code Review

La revisión prioriza riesgo real y resultado del producto. No anteponer cosmética a corrupción de datos, vulnerabilidades, errores financieros o bugs funcionales.

## Severidades

### P0 — Crítico

- Corrupción o pérdida grave de datos.
- Bypass crítico de seguridad o autenticación.
- Cálculo financiero grave con impacto generalizado.

### P1 — Alto

- Tenant leak o acceso cruzado.
- Vulnerabilidad explotable.
- Pricing, totales, impuestos, descuentos o márgenes incorrectos.
- Autorización rota.

### P2 — Medio

- Bug funcional.
- Edge case importante.
- Race condition.
- Query incorrecta, insegura o con impacto relevante de rendimiento.

### P3 — Bajo

- Maintainability.
- Problema de UX o accesibilidad no bloqueante.
- Mejora no crítica.

## Método

Cada finding debe indicar ubicación, escenario reproducible, impacto y corrección sugerida. Verificar tenant scoping, contratos, snapshots, precisión financiera, validación, Policies, idempotencia, migrations, jobs, webhooks, manejo de fallos y cobertura. Si no hay findings, indicar los riesgos residuales y tests que no se ejecutaron.
