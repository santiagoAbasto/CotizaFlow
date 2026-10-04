# Roadmap orientado a validación comercial

El roadmap es progresivo y debe ajustarse con evidencia de clientes. Una feature entra al MVP solo si mejora demostración, onboarding, activación, cierre, retención o resultado económico para los primeros clientes.

## Sprint 0 — Demo comercial

**FOUNDATION: APPROVED.** Baseline `39434c7`, hardening `94e9967`, merge `8c51021`; punto de recuperación local anotado `foundation-v1`. Validación integrada: tenancy 128 tests / 296 assertions; suite completa 160 / 355; calidad y build PASS. Los P3 continúan en el [backlog de foundation](../audit/FOUNDATION_BACKLOG.md).

**SPRINT 0: COMPLETE.** Alcance, contratos y seis slices verticales en [Sprint 0 — Commercial Demo](../sprints/SPRINT_0_COMMERCIAL_DEMO.md). El siguiente paso es validación comercial con los primeros 3 clientes fundadores, no sumar módulos sin evidencia de demanda.

- Autenticación.
- Empresa y usuarios.
- Clientes.
- Productos.
- Listas de precios.
- Cotización con cálculos determinísticos y snapshots.
- PDF.
- Estados de cotización como seguimiento comercial mínimo; sin constructor de pipelines.
- Seguimiento.
- Dashboard básico y accionable.

Objetivo: demostrar el flujo principal y conseguir los primeros 3 clientes fundadores.

Prioridad: un vendedor selecciona cliente y productos y genera una cotización profesional en menos de dos minutos. Orden de entrega: cliente → producto y pricing simple → creación de cotización → PDF y estados → seguimiento → dashboard.

## Sprint 1 — Imports

- XLSX y CSV.
- Clientes.
- Productos.
- Costos.
- Listas de precios.
- Stock.
- Preview, mapping, validación, confirmación y reporte de errores.

## Sprint 2 — WhatsApp Cloud API

- Integración desacoplada.
- Recepción y envío trazables.
- Webhooks seguros, idempotentes y observables.

## Sprint 3 — IA de interpretación y product matching

- Interpretación de consultas.
- Extracción de entidades.
- Sugerencias de coincidencia de producto y compatibilidad.
- Revisión humana y fallback; sin autoridad financiera.

## Sprint 4 — Inteligencia comercial

- Priorización explicable de oportunidades.
- Detección de cotizaciones sin seguimiento.
- Recomendación de siguiente acción.
- Información accionable sobre conversión, respuesta y margen.

## Fuera del MVP

Quedan fuera hasta validar demanda: CRM genérico, ERP completo, contabilidad, marketplace, personalización profunda, automatizaciones decorativas, analítica sin acción y cualquier capacidad que no contribuya a conseguir o retener los primeros clientes.
