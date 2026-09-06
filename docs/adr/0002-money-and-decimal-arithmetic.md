# 0002 — Dinero y aritmética decimal

- **Estado:** accepted
- **Fecha:** 2026-09-04
- **Relacionado:** [pricing](../architecture/PRICING_ENGINE.md), [arquitectura](../architecture/OVERVIEW.md)
- **Alcance:** define la representación. **No** implementa el Pricing Engine ni los Value Objects todavía.

## Contexto y problema

`CLAUDE.md` y `AGENTS.md` prohíben floating point inseguro y exigen cálculos determinísticos, reproducibles y auditables. `PRICING_ENGINE.md` exige además snapshots históricos por línea de cotización. La representación numérica debe decidirse **antes** de la primera migración que almacene importes: cambiarla después obliga a reescribir datos históricos, que por definición no deben recalcularse.

El contexto comercial es Bolivia y la región: BOB y USD como monedas operativas, ARS como caso de alta inflación y baja unidad de valor, con tipos de cambio y costos fraccionarios.

## Opciones consideradas

1. **`float`/`double` en PHP y MySQL.** Rechazada. `0.1 + 0.2 !== 0.3` en binario; un total de cotización no puede depender de eso.
2. **Enteros en unidad mínima (centavos).** Correcta para importes, pero incómoda para lo que no es dinero: cantidades fraccionarias, porcentajes y tipos de cambio necesitan más decimales que la moneda, y mezclar escalas en enteros es una fuente conocida de errores.
3. **`DECIMAL` en MySQL + decimal seguro en PHP.** Elegida.

## Decisión

### Almacenamiento

- Todo valor monetario e intermedio se almacena en **MySQL `DECIMAL`**, nunca `FLOAT`/`DOUBLE`.
- La moneda se almacena aparte, como **`CHAR(3)` ISO 4217** (`BOB`, `USD`, `ARS`). Un importe sin moneda no tiene significado; nunca se asume una por defecto.
- Un importe y su moneda se escriben y leen siempre juntos.

### Precisión propuesta

| Concepto | Tipo | Justificación |
|---|---|---|
| Importes monetarios (unitario, subtotal, total) | `DECIMAL(20,6)` | 14 dígitos enteros, 6 decimales |
| Costos unitarios | `DECIMAL(20,6)` | costos fraccionarios de proveedor |
| Cantidades | `DECIMAL(20,6)` | permite kg, metros, litros |
| Porcentajes (descuento, margen, impuesto) | `DECIMAL(9,6)` | 6 decimales sobre un rango acotado |
| Tipos de cambio | `DECIMAL(20,10)` | ver justificación abajo |

Rounding mode único para todo lo anterior: `ROUND_HALF_UP` (justificado más abajo).

**¿Alcanza `DECIMAL(20,6)`?** Para importes y cantidades, sí. 14 dígitos enteros cubren ~99 billones en unidades monetarias: suficiente incluso para ARS con inflación sostenida, donde un total puede alcanzar millones sin acercarse al límite. 6 decimales exceden la unidad mínima de las tres monedas (BOB y USD usan 2) y dejan margen para costos unitarios fraccionarios y precios por unidad de medida.

**Los tipos de cambio son la excepción y necesitan más.** Un `ARS→USD` puede requerir del orden de `0.0000xxx`; con solo 6 decimales, la conversión perdería dígitos significativos y el error se propagaría al total. Por eso `DECIMAL(20,10)`.

### Aritmética en PHP

- **Nunca `float`.** Ni siquiera de forma transitoria: un cast intermedio anula la garantía.
- Se usa aritmética decimal segura sobre `bcmath` (extensión ya presente) o `brick/math` como `BigDecimal`. La elección concreta de librería se difiere al ADR del Pricing Engine; lo que este ADR fija es la **prohibición de float** y el uso de decimal exacto.
- Los valores viajan como `string` entre PHP y MySQL, no como `float`, para que el driver no degrade la precisión.

### Porcentajes — representación única

Un `Percentage` se almacena y se transporta **como el número que la gente escribe**: 21 % es `21.000000`, no `0.21`.

Se elige esta forma y **no** se mezcla con la de ratio. El motivo es que la ambigüedad `0.21` vs `21` es una de las fuentes de error más frecuentes en cálculo comercial, y un valor como `0.5` es indistinguible entre "0,5 %" y "50 %" al leerlo en la base o en un log. Con la convención declarativa, `0.5` siempre significa medio punto porcentual.

La conversión a factor (`valor / 100`) ocurre **solo dentro** del Value Object, nunca en el código que lo usa. Ninguna otra capa divide ni multiplica por 100.

### Monedas y exponente de presentación

No se asume que toda moneda tenga 2 decimales. El futuro `Currency` expone un **display exponent** propio:

| Moneda | Exponente |
|---|---|
| USD | 2 |
| BOB | 2 |
| ARS | 2 |

Las tres monedas del alcance inicial usan 2, pero el redondeo consulta el exponente de la moneda y nunca una constante global, de modo que incorporar una moneda de 0 o 3 decimales no requiera tocar la aritmética.

El **almacenamiento** conserva siempre `DECIMAL(20,6)`; el exponente rige el redondeo y la presentación, no la columna.

### Cuándo se redondea

Regla: **se preserva precisión durante el cálculo y se redondea solo al persistir un resultado con significado comercial.**

**Secuencia por línea, normativa:**

1. `quantity × unit_price` se calcula con **precisión completa** (`DECIMAL(20,6)`), sin redondeo intermedio.
2. Descuentos, reglas comerciales y conversión de moneda se aplican también con precisión completa. No se redondea entre pasos.
3. El **subtotal de línea** se redondea al exponente de la moneda. Este es el primer valor redondeado, y es el que el cliente ve.
4. Un valor ya redondeado nunca se vuelve a redondear.

### Agregación — el total es la suma de lo visible

**`quote_subtotal = SUM(subtotales de línea ya redondeados)`.**

Se descarta explícitamente calcular el total desde la precisión completa. Ambas opciones son defendibles matemáticamente, pero difieren en centavos, y solo una permite que el cliente sume a mano las líneas del PDF y obtenga el total impreso. Un total que no cierra con sus propias líneas destruye la confianza en la cotización, que es justamente lo que el producto vende.

La diferencia por acumulación de redondeo es conocida y aceptada.

### Impuestos

Política inicial: **impuesto calculado por línea, redondeado por línea, y sumado**.

1. La tasa se aplica sobre el subtotal de línea **ya redondeado** (punto 3 anterior).
2. El impuesto de la línea se redondea al exponente de la moneda.
3. `quote_tax = SUM(impuestos de línea redondeados)`.

Es la misma lógica que la agregación de subtotales, por la misma razón: cada línea es verificable de forma independiente. Se descarta aplicar la tasa sobre el total agregado, porque produce un impuesto que no coincide con la suma de los impuestos por línea.

Cuando aparezcan varias tasas en una misma cotización, se acumulan por tasa y se reportan por separado; esa extensión no cambia la regla por línea.

**Rounding mode: `ROUND_HALF_UP`.** Es el criterio esperado en facturación en la región y el que un cliente puede reproducir con una calculadora. `HALF_EVEN` reduce el sesgo estadístico agregado, pero produce resultados que el usuario percibe como incorrectos al verificarlos a mano; para un producto cuya credibilidad depende de que el cliente confíe en el número, la explicabilidad pesa más.

El redondeo se aplica una sola vez por valor persistido. Nunca se redondea un valor ya redondeado.

### Snapshots históricos

Cada línea de cotización congela, con la precisión de almacenamiento: SKU, descripción, unidad, costo, precio de lista, descuento, precio unitario, margen, subtotal, moneda, tipo de cambio aplicado y la provenance de la regla.

Una cotización histórica **nunca** se recalcula con costos, listas, impuestos ni reglas actuales. Un recálculo solicitado crea una **nueva revisión explícita y auditable**, conservando la anterior intacta.

## Value Objects futuros

A implementar junto al Pricing Engine, no ahora:

- `Money` — importe decimal + `Currency`; prohíbe operar entre monedas distintas sin `ExchangeRate` explícito.
- `DecimalQuantity` — cantidades fraccionarias con unidad.
- `Percentage` — descuentos, márgenes e impuestos, evitando la ambigüedad `0.21` vs `21`.
- `ExchangeRate` — par de monedas, tasa y origen; la conversión registra su provenance.

### Orientación del tipo de cambio — nunca inferida

Un `ExchangeRate` se almacena siempre con las tres piezas explícitas:

```
source_currency   target_currency   rate
```

y se lee **exactamente** como:

> `1 source_currency = rate target_currency`

Ejemplo: `USD → BOB, rate = 6.960000` significa `1 USD = 6,96 BOB`.

La orientación **nunca** se infiere del contexto, del orden alfabético ni de cuál moneda parece "más fuerte". Invertir la orientación de un tipo de cambio es un error que no produce ningún síntoma visible salvo un precio absurdo, y con dos monedas de magnitud similar puede pasar desapercibido.

Para la conversión inversa se calcula `1 / rate` con la precisión declarada de `DECIMAL(20,10)`, y el resultado se marca como derivado en la provenance: nunca se persiste como si fuera una tasa observada.

## Consecuencias y trade-offs

- `DECIMAL` es más lento que un entero en operaciones masivas. Irrelevante al volumen de cotizaciones esperado, y el determinismo vale más.
- Trabajar con strings decimales es más verboso que con `float`. Ese es exactamente el punto: hace visible dónde se opera con dinero.
- Fijar la precisión ahora significa que ampliarla luego requiere migración. Las escalas elegidas dejan margen deliberado para evitarlo.
- Este ADR no autoriza a implementar pricing: solo fija la representación sobre la que se construirá.
