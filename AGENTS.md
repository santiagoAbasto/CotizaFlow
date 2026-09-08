# CotizaFlow Agent Guide

## Project

CotizaFlow es un SaaS B2B multi-tenant para gestión inteligente de consultas, precios, cotizaciones, seguimiento comercial y ventas.

## Business objective

El primer objetivo es conseguir 3 clientes fundadores, luego 10 y después 30, con un objetivo aproximado de USD 4.500–5.000 MRR. El KPI principal no es la cantidad de funcionalidades: es MRR, activación, retención y resultado comercial.

## Mandatory stack

- Laravel 13 y PHP 8.4+
- React, TypeScript, Inertia y Tailwind CSS v4
- MySQL
- Pest, Vitest y Playwright

## Architecture

Mantener controllers delgados y la lógica de negocio fuera de controllers y componentes React. Usar, cuando aporten claridad: Actions, Services, DTOs, Value Objects, Enums, Policies, Form Requests, Jobs, Events, Listeners y Domain Services.

## Critical rules

Nunca permitir tenant leaks, precios calculados por LLM, lógica financiera en frontend, secretos en el repositorio, autorización basada únicamente en ocultar UI, cálculos financieros con floating point inseguro, modificación silenciosa de históricos ni eliminación de tests para obtener verde.

## AI rule

La IA puede interpretar, extraer, clasificar, recomendar y resumir. No decide precio, costo, descuento máximo, margen, impuestos, stock ni totales financieros. Mantener `AIProviderInterface` y `AIOrchestrator`; el dominio conserva la autoridad.

## Before changing code

1. Leer este `AGENTS.md`.
2. Inspeccionar el código relacionado.
3. Revisar solo la documentación correspondiente desde `docs/README.md`; no cargar todo `docs/` sin necesidad.
4. Revisar los tests existentes.
5. Analizar el impacto multi-tenant.
6. Analizar seguridad.
7. Implementar.
8. Ejecutar tests relevantes.
9. Revisar el diff.

## Efficient context and diagrams

- Usar este archivo y `docs/README.md` como mapa; abrir únicamente los documentos vinculados a la tarea.
- La skill local `graphify` está disponible en `.agents/skills/graphify`. Si existe `graphify-out/graph.json`, consultar primero el grafo para preguntas sobre el codebase y abrir código fuente solo para confirmar o completar la respuesta.
- Actualizar Graphify de forma incremental después de cambios estructurales relevantes; no reconstruirlo en cada tarea.
- La skill local `archify` está disponible en `.agents/skills/archify`.
- Usar Archify cuando un mapa de arquitectura, workflow, secuencia, data flow o lifecycle reduzca ambigüedad.
- Guardar la fuente JSON y el HTML validado en `docs/diagrams/`; actualizar el mapa afectado cuando cambie su topología real.
- Los diagramas resumen el sistema, pero el código y los documentos de arquitectura siguen siendo la fuente de verdad.
- Archify es obligatorio para cambios de arquitectura, tenancy, auth/autorización, APIs, webhooks, jobs/events, flujo de datos, cache/storage, servicios externos, IA, pagos, infraestructura, límites de confianza o flujos críticos. Inspeccionar primero el código, generar sólo el tipo de diagrama necesario y validarlo antes de entregarlo.

## Security research and quality gates

- Consultar [Security Research](docs/references/SECURITY_RESEARCH.md) al trabajar en identidad, autorización, tenants, uploads, APIs, secretos, pagos, PII, infraestructura, dependencias o IA. Es inteligencia de amenazas, no una dependencia ni sustituto de OWASP, NIST, RFCs o documentación oficial.
- Antes de finalizar, ejecutar los gates relevantes y reportar resultados: `composer format:check`, `composer analyse`, `php artisan test`, `npm run format:check`, `npm run lint`, `npm run typecheck`, `npm run test`, `npm run build` y Playwright relevante. No inventar scripts ni afirmar gates no ejecutados.
- El código y los tests reales prevalecen sobre diagramas y documentación. Evitar `any`, duplicación, componentes/funciones gigantes, supresiones sin justificación y abstracciones sin una segunda necesidad real.

## Documentation map

- [Visión del producto](docs/product/COTIZAFLOW_VISION.md)
- [ICP](docs/product/ICP.md)
- [Roadmap](docs/product/ROADMAP.md)
- [Arquitectura](docs/architecture/OVERVIEW.md)
- [Multi-tenancy](docs/architecture/TENANCY.md)
- [Pricing Engine](docs/architecture/PRICING_ENGINE.md)
- [Arquitectura de IA](docs/architecture/AI_ARCHITECTURE.md)
- [Seguridad](docs/architecture/SECURITY.md)
- [Desarrollo de features](docs/workflows/FEATURE_DEVELOPMENT.md)
- [Code review](docs/workflows/CODE_REVIEW.md)
- [Checklist de release](docs/workflows/RELEASE_CHECKLIST.md)
- [Diagramas verificables](docs/diagrams/README.md)
- [Grafo persistente del codebase](docs/KNOWLEDGE_GRAPH.md)

## Tests

Antes de finalizar cualquier tarea, ejecutar los tests relacionados e informar los comandos ejecutados y cualquier fallo pendiente. Nunca afirmar que algo está validado sin ejecutar la validación.
