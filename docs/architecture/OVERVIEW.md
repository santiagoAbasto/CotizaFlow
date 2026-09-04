# Arquitectura general

CotizaFlow evolucionará como SaaS multi-tenant modular. El core reutilizable contempla tenants/companies, sucursales, usuarios, roles y permisos, planes, límites, notificaciones, archivos, auditoría, settings, integraciones, API keys, webhooks, uso de IA e imports/exports.

Los módulos de negocio abarcan leads, clientes, contactos, catálogo, proveedores, compatibilidad, precios, costos, stock, cotizaciones, oportunidades, seguimientos, pedidos, conversaciones, mensajes y automatizaciones.

## Stack obligatorio

- Laravel 13, PHP 8.4+, MySQL, Redis cuando corresponda, queues y scheduler.
- React, TypeScript, Inertia y Tailwind CSS v4.
- Pest, Vitest y Playwright.

## Límites y responsabilidades

- Controllers coordinan HTTP; Form Requests validan; Policies autorizan.
- Actions y Domain Services expresan casos de uso y reglas de negocio.
- DTOs, Value Objects y Enums hacen explícitos contratos e invariantes.
- Query Objects encapsulan consultas complejas o reutilizables.
- Jobs, Events y Listeners desacoplan trabajo asíncrono y efectos secundarios.
- React presenta y captura interacción; no es autoridad financiera ni de seguridad.
- Integraciones externas viven detrás de interfaces/adapters y no se llaman directamente desde controllers.

Toda decisión debe preservar aislamiento por tenant, auditabilidad, idempotencia donde corresponda, observabilidad y capacidad de prueba. Consultar [tenancy](TENANCY.md), [pricing](PRICING_ENGINE.md), [IA](AI_ARCHITECTURE.md) y [seguridad](SECURITY.md).
