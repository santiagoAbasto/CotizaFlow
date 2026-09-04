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
3. Revisar la documentación correspondiente.
4. Revisar los tests existentes.
5. Analizar el impacto multi-tenant.
6. Analizar seguridad.
7. Implementar.
8. Ejecutar tests relevantes.
9. Revisar el diff.

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

## Tests

Antes de finalizar cualquier tarea, ejecutar los tests relacionados e informar los comandos ejecutados y cualquier fallo pendiente. Nunca afirmar que algo está validado sin ejecutar la validación.
