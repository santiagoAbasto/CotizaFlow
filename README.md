<div align="center">
<img src="docs/readme/banner.svg" alt="CotizaFlow — Cotiza más rápido, da seguimiento y no deja cotizaciones olvidadas" width="100%">
<br/>

**Sistema operativo de cotizaciones para distribuidores y empresas B2B: clientes, productos, precios, PDF y seguimiento en un solo flujo.**

<img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white&labelColor=1E3A5F">
<img alt="PHP 8.4" src="https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white&labelColor=1E3A5F">
<img alt="React 19" src="https://img.shields.io/badge/React-19-61DAFB?style=for-the-badge&logo=react&logoColor=1E3A5F&labelColor=1E3A5F">
<img alt="Multiempresa" src="https://img.shields.io/badge/multiempresa-tenant_por_company-5B8DEF?style=for-the-badge&labelColor=1E3A5F">
<br/>
<img alt="Inertia 3" src="https://img.shields.io/badge/Inertia-3-9553E9?style=flat-square">
<img alt="Tailwind CSS 4" src="https://img.shields.io/badge/Tailwind_CSS-4-06b6d4?style=flat-square&logo=tailwindcss&logoColor=white">
<img alt="Pest" src="https://img.shields.io/badge/Pest-tests-F97316?style=flat-square">
<img alt="Playwright" src="https://img.shields.io/badge/Playwright-E2E-2ead33?style=flat-square">
<img alt="TypeScript" src="https://img.shields.io/badge/TypeScript-7-3178c6?style=flat-square&logo=typescript&logoColor=white">
</div>

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-indice.svg" width="24" height="24" alt=""> Contenido

- [El problema](#-el-problema)
- [La solución](#-la-solución)
- [Funcionalidades](#-funcionalidades)
- [Arquitectura](#-arquitectura)
- [Flujos de trabajo](#-flujos-de-trabajo)
- [Modelo de datos](#-modelo-de-datos)
- [Criterios y decisiones](#-criterios-y-decisiones)
- [Stack](#-stack)
- [Estructura del repo](#-estructura-del-repo)
- [Puesta en marcha](#-puesta-en-marcha)
- [Pruebas y calidad](#-pruebas-y-calidad)
- [Documentación](#-documentación)
- [Despliegue](#-despliegue)
- [Estado y pendientes](#-estado-y-pendientes)
- [Licencia y autoría](#-licencia-y-autoría)

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-problema.svg" width="24" height="24" alt=""> El problema

En un equipo comercial B2B, una cotización sigue un recorrido que se rompe fácil: el cliente consulta, alguien busca el producto y su precio, se arma un PDF o una planilla, se envía, y después nadie vuelve a llamar. Las cotizaciones enviadas que no reciben respuesta son ventas que se pierden sin que nadie lo note. La documentación de producto apunta a ese punto como el problema visible.

## <img src="docs/readme/ic-solucion.svg" width="24" height="24" alt=""> La solución

CotizaFlow mantiene en un solo lugar los clientes, los productos, las listas de precios, las cotizaciones con su PDF y los seguimientos pendientes, con un dashboard que prioriza qué llamar hoy.

| Actor | Qué hace |
|---|---|
| **Equipo comercial** | Crea cotizaciones con precios de la lista del cliente, envía el PDF, registra la respuesta y agenda seguimientos. |
| **Administración de la empresa** | Gestiona productos, listas de precios, clientes y usuarios de su empresa. |

Cada empresa es un tenant aislado: un usuario nunca ve datos de otra empresa, aunque tenga cuenta en varias.

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-funcionalidades.svg" width="24" height="24" alt=""> Funcionalidades

<details open>
<summary><b>Cotizaciones</b></summary>

- Borrador con búsqueda de cliente y de producto, precio resuelto desde la lista del cliente y totales calculados en el servidor.
- Vista previa antes de emitir y PDF de la cotización.
- Estados: **borrador, enviada, aceptada, rechazada y vencida**. Una cotización vencida se marca automáticamente.

</details>

<details open>
<summary><b>Seguimiento y dashboard</b></summary>

- Seguimientos asociados a cada cotización, con estados **pendiente, completado y cancelado**.
- Lista de seguimientos pendientes y dashboard comercial con acciones concretas.

</details>

<details open>
<summary><b>Catálogo y precios</b></summary>

- Clientes y productos con estado de activación; unidades de producto.
- Listas de precios con productos y precio por lista, y resolución de precio por cliente.

</details>

<details>
<summary><b>Empresas y acceso</b></summary>

- Login, selección de empresa activa y membresías con rol y estado por empresa.
- Transferencia de titularidad de una empresa con autorización explícita.

</details>

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-arquitectura.svg" width="24" height="24" alt=""> Arquitectura

<p align="center">
  <img src="docs/readme/flujo.svg" alt="Cómo funciona: equipo comercial, empresa activa con aislamiento por tenant, cotización con precios y estados, y seguimiento" width="100%">
</p>

Monolito modular Laravel con Inertia para el frontend en React. El dominio se organiza por contexto en `app/Domain/` (Clientes, Productos, Precios, Cotizaciones, Seguimientos, Dashboard, Dinero y Tenancy). Un `TenantContext` fija la empresa activa y un global scope la aplica a las consultas.

```mermaid
flowchart TB
    U(["Equipo comercial"]) -->|"HTTPS · Inertia + React"| R["Rutas web<br/>auth · guest"]
    R --> M["Middleware de tenant<br/>empresa activa por membresía"]
    M --> D["Dominio<br/>Quotes · Pricing · FollowUps<br/>Customers · Products · Dashboard"]
    D --> T["TenantContext<br/>global scope por company_id"]
    T --> DB[("Base de datos<br/>MySQL")]
    D --> P["PDF de cotización<br/>DomPDF"]
    D -.-> J["Cola y tareas<br/>vencimiento de cotizaciones"]
```

El `company_id` que llega desde el cliente es un dato no confiable. La única ruta autorizada para cambiar de empresa es `ActivateCompanyForUser`, que verifica la membresía del usuario autenticado (ver [`docs/architecture/TENANCY.md`](docs/architecture/TENANCY.md)).

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-flujos.svg" width="24" height="24" alt=""> Flujos de trabajo

### Ciclo de una cotización

Las transiciones se validan en `TransitionQuoteStatus`; una cotización aceptada o rechazada ya no vuelve al borrador.

```mermaid
stateDiagram-v2
    [*] --> Borrador
    Borrador --> Enviada: enviar al cliente
    Enviada --> Aceptada: el cliente acepta
    Enviada --> Rechazada: el cliente rechaza
    Enviada --> Vencida: pasa la fecha de validez
    Aceptada --> [*]
    Rechazada --> [*]
    Vencida --> [*]
```

### Emisión de una cotización

```mermaid
sequenceDiagram
    autonumber
    participant C as Equipo comercial
    participant W as Panel (Inertia)
    participant Q as Dominio de cotizaciones
    participant P as Precios
    participant DB as Base de datos
    C->>W: elige cliente y productos
    W->>Q: vista previa
    Q->>P: precio por lista del cliente
    P-->>Q: precio resuelto por línea
    Q-->>W: totales calculados en el servidor
    C->>W: emite y envía
    W->>Q: transición borrador → enviada
    Q->>DB: guarda cotización, número y PDF
    Q-->>C: cotización enviada
```

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-datos.svg" width="24" height="24" alt=""> Modelo de datos

```mermaid
erDiagram
    COMPANY ||--o{ MEMBERSHIP : tiene
    USER ||--o{ MEMBERSHIP : "pertenece por"
    COMPANY ||--o{ CUSTOMER : contiene
    COMPANY ||--o{ PRODUCT : contiene
    COMPANY ||--o{ PRICE_LIST : contiene
    PRICE_LIST ||--o{ PRODUCT_PRICE : "fija precio"
    PRODUCT ||--o{ PRODUCT_PRICE : "tiene precio"
    COMPANY ||--o{ QUOTE : emite
    CUSTOMER ||--o{ QUOTE : recibe
    QUOTE ||--o{ QUOTE_ITEM : contiene
    QUOTE ||--o{ FOLLOW_UP : genera
    COMPANY ||--o{ QUOTE_SEQUENCE : numera

    COMPANY { bigint id PK string name }
    USER { bigint id PK string email }
    MEMBERSHIP { bigint id PK string role string status }
    CUSTOMER { bigint id PK bigint company_id }
    PRODUCT { bigint id PK bigint company_id }
    PRICE_LIST { bigint id PK bigint company_id }
    PRODUCT_PRICE { bigint id PK bigint price_list_id }
    QUOTE { bigint id PK string status bigint company_id }
    QUOTE_ITEM { bigint id PK bigint quote_id }
    FOLLOW_UP { bigint id PK string status bigint quote_id }
    QUOTE_SEQUENCE { bigint id PK bigint company_id }
```

Toda entidad comercial lleva `company_id` de forma explícita. Los importes se guardan como decimales y se calculan con aritmética decimal (ver ADR 0002).

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-criterios.svg" width="24" height="24" alt=""> Criterios y decisiones

| Decisión | Por qué |
|---|---|
| **Company como único tenant** | Es el límite de aislamiento. Una sucursal sería una subdivisión dentro de la empresa, nunca un tenant. |
| **`User` global y `Membership` por empresa** | La misma persona puede trabajar en varias empresas con un rol distinto en cada una. El rol nunca vive en `User`. |
| **Empresa activa solo por membresía** | Un `company_id` del cliente no se confía; se valida contra la membresía del usuario autenticado. Ver ADR 0001. |
| **Dinero con aritmética decimal** | Los totales no dependen de floats. Ver ADR 0002. |
| **Precios resueltos en el servidor** | El precio sale de la lista del cliente y del producto, no de lo que manda el formulario. |
| **Reglas de negocio en el dominio** | Transiciones de estado, numeración y cálculo viven en `app/Domain/`, no en los controladores. |
| **Inertia con React** | Una sola aplicación desplegable con interacción en el panel, sin API separada para cada pantalla. |

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-stack.svg" width="24" height="24" alt=""> Stack

Versiones tomadas de `composer.json` y `package.json`.

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | PHP · Laravel | `^8.4` · `^13.17` |
| Puente SPA | Inertia Laravel · Inertia React | `^3.3` · `^3.7.0` |
| Frontend | React · TypeScript | `^19.2.8` · `^7.0.2` |
| Build y estilos | Vite · Tailwind CSS | `^8.0.0` · `^4.0.0` |
| PDF y dinero | Laravel Dompdf · brick/math | `^3.1` · `^0.18` |
| Datos | MySQL (`DB_CONNECTION=mysql` en `.env.example`) | — |
| Pruebas | Pest · Vitest · Playwright | — · `^5.0.0` · `^1.63.0` |
| Calidad | oxlint · Prettier | `^1.82.0` · `^3.9.6` |

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-estructura.svg" width="24" height="24" alt=""> Estructura del repo

```text
app/
├── Domain/                 Customers · Products · Pricing · Quotes · FollowUps · Dashboard · Money · Tenancy
├── Http/Controllers/       controladores delgados
├── Http/Middleware/        tenant y acceso
└── Models/                 Company · Membership · Customer · Product · PriceList · Quote · FollowUp…
database/migrations/        empresas, membresías, clientes, productos, listas de precios, cotizaciones, seguimientos
resources/js/              frontend React + Inertia
routes/web.php              rutas de login, empresas, catálogo, cotizaciones y seguimientos
tests/                      Feature · Unit · Browser (Playwright) · Fixtures
docs/                       producto, arquitectura, ADR, diagramas, workflows, comercial
```

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-arranque.svg" width="24" height="24" alt=""> Puesta en marcha

Requisitos: PHP 8.4 o superior, Composer, Node.js y MySQL.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
```

- Las variables de base de datos y de correo se configuran en `.env` (no versionado).
- Los comandos de la aplicación están en `composer.json` y `package.json`.

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-pruebas.svg" width="24" height="24" alt=""> Pruebas y calidad

```bash
npm run typecheck     # tsc --noEmit
npm run lint          # oxlint con reglas de React y accesibilidad
npm run test          # vitest run
npm run build         # vite build
php artisan test      # Pest
npx playwright test   # pruebas E2E en tests/Browser
```

- Hay pruebas Feature, Unit y de navegador (clientes, productos y precios, cotizaciones, seguimientos y dashboard).
- **No se ejecutaron para este README**; el número de pruebas no se documenta aquí.

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-libro.svg" width="24" height="24" alt=""> Documentación

| Documento | Qué encuentras |
|---|---|
| [`docs/README.md`](docs/README.md) | Índice de toda la documentación |
| [`docs/product/`](docs/product/) | Visión, ICP y roadmap |
| [`docs/architecture/`](docs/architecture/) | Visión general, multi-tenancy, motor de precios, IA y seguridad |
| [`docs/adr/`](docs/adr/) | Decisiones de arquitectura (ADR 0001 y 0002) |
| [`docs/diagrams/`](docs/diagrams/) | Diagramas verificables con Archify |
| [`docs/workflows/`](docs/workflows/) | Desarrollo de features, revisión de código y checklist de release |
| [`docs/commercial/`](docs/commercial/) | Activos para los primeros clientes fundadores |

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-despliegue.svg" width="24" height="24" alt=""> Despliegue

No documentado en el repositorio. No se encontraron Dockerfile, Compose ni workflows de CI en la raíz.

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-estado.svg" width="24" height="24" alt=""> Estado y pendientes

- Último commit: **04-10-2026** (`70d2d7a`, «fix: load vite react refresh preamble»), precedido por documentación de la validación con clientes fundadores.
- Fase: **validación comercial** con clientes fundadores; la documentación de producto la describe como fase de validación, no de operación en producción.
- Pendientes documentados: ver [`docs/product/ROADMAP.md`](docs/product/ROADMAP.md) y [`docs/commercial/VALIDATION_BACKLOG.md`](docs/commercial/VALIDATION_BACKLOG.md).

<img src="docs/readme/divider.svg" alt="" width="100%">

## <img src="docs/readme/ic-licencia.svg" width="24" height="24" alt=""> Licencia y autoría

Desarrollado por el **Ing. Santiago Abasto**.


El repositorio no incluye archivo de licencia. Código propietario; no se distribuye sin autorización.
