# Pricing Engine

## Propósito

El Pricing Engine es un servicio de dominio desacoplado que produce precios determinísticos, reproducibles, auditables y testeables. Nunca delega decisiones financieras a un LLM ni al frontend.

## Entrada conceptual

El contexto de evaluación incluye:

- customer;
- product;
- price list;
- quantity;
- branch;
- currency;
- supplier cost;
- commercial rules;
- discount;
- minimum margin.

Los importes y porcentajes deben representarse con Value Objects o precisión decimal/entera definida; nunca con floating point inseguro.

## Resultado explicable

Además del precio, el resultado incluye provenance suficiente para explicar y auditar:

- costo utilizado y su fuente;
- moneda y conversión aplicada, si existe;
- lista de precios seleccionada;
- regla comercial que determinó el precio;
- descuento solicitado, permitido y aplicado;
- margen resultante y mínimo exigido;
- advertencias o reglas rechazadas.

Las reglas deben tener precedencia explícita y producir un trace estable. El motor rechaza contextos incompletos o inconsistentes en lugar de inventar valores.

## Históricos y snapshots

Cada línea de cotización conserva snapshots de SKU, descripción, unidad, costo, precio de lista, descuento, precio unitario, margen y subtotal, además de la provenance necesaria. Las revisiones conservan su propia versión.

Nunca recalcular una cotización histórica con productos, costos, listas, impuestos o reglas actuales. Un recálculo solicitado crea una nueva revisión explícita y auditable.
