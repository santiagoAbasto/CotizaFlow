# Claude Code Guide — CotizaFlow

CotizaFlow es un SaaS B2B multi-tenant orientado a convertir consultas en cotizaciones, seguimientos y ventas. El resultado comercial —MRR, activación y retención— tiene prioridad sobre la cantidad de funcionalidades.

## Antes de implementar

1. Leer [el índice documental](docs/README.md) y únicamente los documentos relacionados con la tarea.
2. Inspeccionar el código y los tests existentes.
3. Presentar `CURRENT STATE`, `GAP`, `RISKS` y `PLAN`; para cambios grandes, incluir `FILES`.
4. Confirmar el impacto de multi-tenancy, autorización, datos históricos y cálculos financieros.

## Reglas fundamentales

- Mantener una UX profesional, clara, rápida, accesible y sin decoración innecesaria.
- No crear features que no ayuden a vender, activar, retener, reducir trabajo o errores, proteger margen o mejorar trazabilidad.
- Aplicar tenant scoping y autorización en backend; nunca confiar en IDs de empresa enviados por frontend ni en UI oculta.
- Mantener los cálculos financieros determinísticos, reproducibles, auditables y testeables; no usar floating point inseguro.
- Preservar snapshots de cotizaciones e históricos.
- Mantener `AIProviderInterface` y `AIOrchestrator`; la IA interpreta y recomienda, pero nunca es autoridad sobre precio, costo, descuentos, margen, impuestos, stock o totales.
- Mantener controllers delgados y la lógica de negocio fuera de React.
- No commitear secretos ni eliminar tests para obtener verde.

## Contexto eficiente y Archify

Usar `CLAUDE.md` y `docs/README.md` como índices en lugar de cargar toda la documentación. Archify está disponible para Claude Code mediante `.claude/skills/archify`, enlazada a la única copia versionada en `.agents/skills/archify`. Usarla cuando un diagrama reduzca la cantidad de contexto necesaria para comprender arquitectura, workflows, secuencias, flujos de datos o estados. Conservar sus fuentes JSON y HTML validados en [`docs/diagrams/`](docs/diagrams/README.md).

## Validación y reporte

Ejecutar los tests relevantes y revisar el diff antes de finalizar. Reportar `IMPLEMENTED`, `TEST RESULTS`, `SECURITY REVIEW`, `TECHNICAL DEBT`, `RISKS` y `NEXT BEST STEP`. No declarar validación sin haber ejecutado el comando correspondiente.

Referencias principales: [visión](docs/product/COTIZAFLOW_VISION.md), [roadmap](docs/product/ROADMAP.md), [arquitectura](docs/architecture/OVERVIEW.md), [tenancy](docs/architecture/TENANCY.md), [pricing](docs/architecture/PRICING_ENGINE.md), [IA](docs/architecture/AI_ARCHITECTURE.md), [seguridad](docs/architecture/SECURITY.md) y [workflow](docs/workflows/FEATURE_DEVELOPMENT.md).
