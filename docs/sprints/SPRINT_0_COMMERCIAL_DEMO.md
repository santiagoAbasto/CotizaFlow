# Sprint 0 — Commercial Demo

## Goal

Crear un demo comercial real de CotizaFlow donde un vendedor B2B pueda seleccionar un cliente, agregar productos y generar una cotización profesional en menos de dos minutos. Debe ayudar a conseguir los primeros tres clientes fundadores.

Estado al 2026-09-06: **FOUNDATION APPROVED · SPRINT 0 READY**. Ready significa listo para implementar, no funcionalidades terminadas. Base: merge `8c51021`, con `39434c7` y `94e9967` preservados; tag local `foundation-v1`.

## Commercial Demo Story

Un vendedor recibe una consulta fuera de la aplicación. Inicia sesión, confirma la empresa activa, busca o crea al cliente, selecciona productos y una lista compatible con la moneda, ingresa cantidades y descuento y ve los precios y totales confirmados por backend. Guarda, genera el PDF, registra manualmente que lo envió y programa un seguimiento. Desde Seguimientos identifica qué atender hoy y desde Dashboard abre las cotizaciones pendientes.

El happy path completo es: **LOGIN → EMPRESA → CLIENTE → PRODUCTO → LISTA DE PRECIOS → COTIZACIÓN → PDF → SEGUIMIENTO → DASHBOARD**. El cronómetro de menos de dos minutos comienza al abrir Nueva cotización, con sesión y catálogo preparados, y termina al generar el PDF de una cotización de tres líneas. Registrar la prueba manual y su duración; no presentar una meta como resultado medido.

## In Scope

- Acceso web mínimo con identidad global existente y selección explícita de empresa mediante membership activa. Usuarios/empresas del demo pueden provisionarse por el mecanismo interno documentado; no requiere signup público.
- Clientes, productos y listas de precios simples utilizables de punta a punta.
- Creación de cotizaciones con cálculos determinísticos, snapshots y estados explícitos.
- Una plantilla PDF profesional, descarga autorizada y registro manual de envío.
- Seguimientos manuales y dashboard de acciones pendientes.
- Datos ficticios reproducibles en dos empresas para demostración y pruebas de aislamiento.

## Out of Scope

WhatsApp Cloud API, IA, billing, subscriptions, imports complejos, stock avanzado, proveedores avanzados, automatizaciones, API pública, reporting complejo, motor de FX y Pricing Engine completo. Tampoco un diseñador PDF, constructor de pipelines, portal público de aceptación ni módulos vacíos.

P3 de foundation permanecen OPEN en el [backlog](../audit/FOUNDATION_BACKLOG.md): autorización de ownership transfer, frontera raw query de Membership y futuras rutas públicas tenant-owned. No se exponen esas superficies en este sprint.

## Domain Entities

Todas las entidades comerciales reutilizan la infraestructura tenant-owned aprobada; User sigue siendo identidad global y Membership vincula usuario y Company. Company es tenant, no sucursal.

| Entidad | Datos y alcance mínimo |
| --- | --- |
| Customer | Nombre/razón social, identificación fiscal opcional, email, teléfono, WhatsApp, condición comercial, notas, status. Extender el modelo existente. |
| Product | SKU, nombre, descripción, categoría simple, unidad, costo base y precio base con moneda explícita, status. SKU único por company. |
| PriceList | Nombre, moneda, activa/inactiva, ajuste porcentual simple sobre precio base. Sin reglas por proveedor o motor de reglas. |
| Quote | Customer, vendedor, número único por company, fecha, expiración, status, currency, notes, terms, subtotal, discount, tax, total y referencia a lista/snapshot de cálculo. |
| QuoteItem | Product, SKU y descripción snapshot, quantity, unit, precio unitario snapshot, discount y subtotal. Conservar también costo/precio de lista, tasa/impuesto y provenance necesarios para reproducir el cálculo según ADR 0002. |
| FollowUp | Quote, customer, owner/user, fecha, nota, status y `next_follow_up_at`. Customer debe coincidir con el de la quote. |

La cotización/PDF conserva los datos comerciales relevantes de empresa y cliente para evitar que cambios posteriores alteren un documento emitido. El vínculo al producto sirve para trazabilidad, no para recalcular históricos.

## Core Flows

1. Login → membership activa → empresa seleccionada. Sin selección no se consulta dominio comercial. Mantener las respuestas 403/409 existentes y evitar fallback silencioso.
2. Dashboard → Nueva cotización → buscar/crear cliente sin perder el borrador → agregar productos con búsqueda rápida → lista/moneda → cantidades/descuentos → totales del servidor → guardar.
3. Cotización guardada → generar/descargar PDF → marcar enviada → programar seguimiento. Descargar no implica envío ni visualización por el cliente.
4. Seguimientos de hoy/vencidos → abrir cotización → registrar resultado → completar o programar siguiente acción.
5. Dashboard → filtro accionable → cotización o seguimiento específico.

### Vertical Slices

Cada slice incluye persistencia, Action/validación, Policy, UI usable, estados de error y tests. No dividir por capas horizontales.

| Slice | Entrega usable | Criterio de salida |
| --- | --- | --- |
| 1 — Customer end-to-end | Como prerrequisito, login/logout y selección de Company existentes a nivel de dominio; luego listado, búsqueda, alta, edición e inactivación de clientes. | Usuario autenticado opera clientes de su empresa; requests manipulados y búsquedas ajenas rechazados. |
| 2 — Product + simple pricing end-to-end | Catálogo mínimo y gestión de listas desde el contexto de productos/configuración; precio explicable calculado en backend. | Crear producto/lista, resolver precio de la misma moneda y probar cantidades/descuentos/rounding con decimal seguro. |
| 3 — Quote creation end-to-end | Flujo principal optimizado: cliente, productos, lista, cantidades, descuento, totales y guardado transaccional. | Quote y líneas coherentes con snapshots; un fallo no deja una cotización parcial. Es la prioridad comercial del sprint. |
| 4 — PDF + status | PDF consistente y transiciones autorizadas con origen manual trazable. | PDF refleja snapshots y sólo es descargable por usuarios autorizados; se registra enviada sin inventar señales externas. |
| 5 — Follow-up | Programar, listar hoy/vencidos, registrar nota y completar seguimiento. | Vendedor identifica y atiende oportunidades pendientes sin automatización. |
| 6 — Dashboard | Métricas filtrables que abren trabajo pendiente. | Datos reconciliados con cotizaciones y seguimientos reales por tenant y moneda. |

## Routes / Screens

Propuesta de pantallas/rutas web autenticadas con React, TypeScript e Inertia; no son una API pública ni endpoints ya implementados.

| Pantalla | Ruta propuesta y acciones |
| --- | --- |
| Acceso | `/login`, logout; selección de empresa en `/companies/select`, con convención de nombres `tenancy.*` existente. |
| Dashboard | `/dashboard`; acciones Nueva cotización y filtros hacia pendientes. |
| Clientes | `/customers`, `/customers/create`, `/customers/{customer}/edit`; búsqueda y status. |
| Productos | `/products`, alta/edición; listas accesibles desde Productos o configuración mínima, sin módulo vacío de navegación. |
| Listas | `/price-lists`, alta/edición simple; también selector contextual en cotización. |
| Cotizaciones | `/quotes`, `/quotes/create`, `/quotes/{quote}`; edición de draft, cambio de estado y descarga PDF autorizados. |
| Seguimientos | `/follow-ups`; filtros hoy/vencidos y alta contextual desde quote. |

Navegación visible: **Dashboard · Clientes · Productos · Cotizaciones · Seguimientos**. Publicar cada entrada cuando su slice sea usable. Desktop-first, responsive, accesible y keyboard-friendly; sin gradients gratuitos, glassmorphism ni animaciones decorativas. Feedback de guardado, loading, error y vacío útil; no sidebar con módulos futuros.

## Business Rules

- La creación de cotización es el centro; minimizar cambios de pantalla y preservar el borrador al crear cliente. Prevenir doble envío/guardado duplicado.
- Desactivar clientes/productos/listas impide nuevas selecciones, sin borrar ni modificar históricos.
- Cantidad positiva, importes no negativos y descuentos entre 0 y 100; validar escala, overflow y autorización en backend. Un ajuste de lista no puede producir un precio negativo.
- Pricing simple: precio base → ajuste opcional de lista → descuento de línea → importe de línea. Lista/producto/quote deben compartir moneda; rechazar mezclas, sin FX implícito.
- El descuento de cabecera resume los descuentos de líneas; no se aplica una segunda vez al total. La semántica de subtotal/descuento/impuestos debe ser consistente entre pantalla, persistencia y PDF.
- Status mínimo: `draft`, `sent`, `viewed`, `accepted`, `rejected`, `expired`. `viewed` queda reservado y no se activa sin señal real.
- Transiciones de Sprint 0: draft → sent; sent → accepted/rejected; sent vencida → expired con evaluación de vigencia en backend. La expiración se refleja sin requerir automatización; impedir aceptación fuera de vigencia. Registrar actor/fecha/origen de cambios.
- Accepted/rejected se registran manualmente por un usuario autorizado, sin presentarlo como aceptación digital del cliente. No permitir modificaciones financieras silenciosas de cotizaciones emitidas: conservar original y usar copia/revisión explícita si se necesita recotizar.
- Número de quote único por company asignado de forma segura ante concurrencia. Quote y líneas se guardan en una transacción.
- PDF incluye empresa, cliente, número, fecha, vigencia, líneas, subtotal, descuento, impuestos, total, términos y observaciones. Una plantilla consistente; contenido escapado y archivos privados.
- Follow-up manual: pendiente/completado/cancelado; fecha y zona horaria explícitas para clasificar hoy/vencido; responsable con membership activa en la company.
- Dashboard: abiertas = sent/viewed vigentes; vencidas = emitidas fuera de vigencia sin cierre; aceptadas = accepted. Valor cotizado excluye drafts; valor pendiente corresponde a abiertas. Mostrar filtros/período y sumar importes por moneda, nunca ARS+BOB+USD juntos. Seguimientos de hoy/vencidos incluyen sólo pendientes.

## Money Rules

Norma: [ADR 0002 — Money and decimal arithmetic](../adr/0002-money-and-decimal-arithmetic.md).

- MySQL `DECIMAL(20,6)` para importes/costos/cantidades, `DECIMAL(9,6)` para porcentajes, moneda ISO `CHAR(3)`. Intercambiar strings decimales; nunca float en cálculos financieros de PHP/JavaScript ni como paso intermedio.
- 21 % se representa como `21.000000`; conversión a factor centralizada. Usar decimal seguro y precisión intermedia suficiente; elegir y declarar dependencia durante Slice 2 conforme al ADR.
- `ROUND_HALF_UP`, según exponente de la moneda: BOB/USD/ARS = 2. Conservar precisión durante cantidad × precio, ajuste y descuento; redondear subtotal comercial por línea. Subtotal de quote = suma de líneas redondeadas.
- Impuesto por línea sobre subtotal redondeado, redondeado por moneda; impuesto de quote = suma de impuestos de líneas. Total = suma de subtotales netos + impuestos, sin restar otra vez los descuentos resumidos.
- Tasas/condiciones fiscales explícitas y validadas; no inferir reglas contables por moneda ni afirmar que la cotización sea factura fiscal.
- Snapshots y provenance guardan costo/precio usado, lista/ajuste, descuento y reglas relevantes. No recalcular documento histórico con catálogo actual.
- Backend es autoridad de totales; la UI solicita/revela resultados del cálculo compartido de servidor y presenta estado pendiente mientras recalcula. No confiar en totales enviados por el navegador.

## Tenant Rules

Customer, Product, PriceList, Quote, QuoteItem y FollowUp usan `BelongsToTenant`, builders/contexto y Policies aprobados. No crear una implementación paralela ni confiar en `company_id` de body/query/header.

Validar pertenencia de cada relación, incluida quote-item-product y follow-up-quote-customer. Los usuarios relacionados se validan por membership de la Company, ya que User es global. No reutilizar builders entre contextos; conservar rechazo `TenantContextChanged`.

Auth y resolución de tenant preceden binding. Recursos extranjeros responden sin revelar su existencia; PDF, búsquedas y acciones tienen autorización backend. Scope garantiza aislamiento; Policy garantiza permiso. Usar roles existentes owner/admin/sales y definir matriz de acciones antes de cada slice, deny-by-default. Archivos privados con descarga autorizada, sin endpoint público por token en este sprint.

## Acceptance Criteria

- Happy path completo ejecutable con datos ficticios y una membership válida; logout y cambio explícito de empresa funcionan.
- Un vendedor entrenado genera el PDF de una quote de tres líneas en menos de dos minutos bajo el escenario definido, con evidencia de medición.
- Quote creada persiste cliente/productos/condiciones y totales coherentes; edición del catálogo no altera snapshots ni PDF emitido.
- Backend rechaza relaciones/IDs extranjeros, cantidades inválidas, monedas incompatibles y totales manipulados.
- PDF coincide con lo visible, sólo se descarga con permiso y no activa falsamente sent/viewed.
- Seguimientos permiten responder qué cotizaciones seguir hoy; dashboard conduce a esos registros y sus cifras son verificables por moneda.
- Estados vacíos, loading, errores, teclado y responsive evaluados; sin errores de consola ni navegación a módulos sin implementar.

## Test Plan

- Pest: aislamiento Customer/Product/PriceList/Quote/QuoteItem/FollowUp en lectura, búsquedas, escritura, borrado y relaciones; autorización por roles/memberships y PDF. Mantener toda la suite de foundation.
- Pest: cálculos decimales, cantidades fraccionarias, límites/overflow, descuentos, redondeo, impuestos y consistencia entre suma visible y total; snapshots tras cambios de catálogo; transiciones y expiración; integridad transaccional y numeración concurrente; filtros de seguimiento/dashboard por tenant, fecha y moneda.
- Playwright: login → seleccionar empresa → cliente → producto/lista → nueva quote → agregar producto → guardar → PDF → seguimiento; verificar descarga, errores visibles y aislamiento relevante.
- Vitest sólo para lógica frontend real: helpers/hooks/estados con comportamiento; sin duplicar autoridad financiera en React. Incorporar tooling faltante cuando el slice lo necesite.
- Tests sobre MySQL `cotizaflow_testing` mediante `TestDatabaseGuard`; nunca saltar guardias ni usar desarrollo. Ejecutar Pest, Pint, Composer, TypeScript, build y Playwright/Vitest relevantes al entregar cada slice.
- Revisión manual: PDF legible, datos coherentes, navegación con teclado y viewport reducido; cronometrar demo. Distinguir pruebas ejecutadas de pendientes.

## Definition of Done

Cada slice termina usable, autorizado, validado y testeado, con error/loading/empty states y documentación mínima actualizada. No eliminar tests para obtener verde. Contratos financieros determinísticos y tenant isolation sin regresiones, sin secretos ni errores de consola.

Sprint completo: seis slices entregados, happy path y criterios de aceptación demostrados, suites relevantes verdes, demo reproducible y diff revisado. Sin módulos fuera de alcance ni métricas simuladas. No considerar cumplida una validación que no se ejecutó.

## IMPLEMENTATION OWNERSHIP

Propuesta de responsabilidades para futuras tareas, sin iniciar desarrollo en esta ronda:

- **Claude Code:** UX/interacción, flujos frontend, experiencia de cotización, presentación de dashboard y revisión visual del PDF.
- **Codex:** modelos de dominio, migraciones, cálculos, Policies, tests, corrección tenant y Actions/endpoints backend internos.

Por slice, acordar primero contrato de datos, permisos, errores y archivos asignados. Un responsable por archivo durante cada entrega; secuenciar backend y frontend cuando se solapen. Integrar con pruebas y review antes de empezar el siguiente slice. La división no autoriza edición simultánea sobre los mismos archivos.

**Next implementation step: SLICE 1 — CUSTOMER END-TO-END.**
