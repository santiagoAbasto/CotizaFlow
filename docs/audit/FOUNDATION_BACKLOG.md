# Foundation backlog

Estos findings no bloquean el baseline aprobado, pero deben resolverse antes de ampliar las superficies indicadas.

## P2-01 — Tenant predicate duplication

`TenantQueryBuilder::applyBeforeQueryCallbacks()` puede añadir repetidamente `company_id = ?` al reutilizar builders. Se observaron SQL redundante, bindings acumulados y `union()` roto. El comportamiento es fail-closed y no existe un tenant leak confirmado.

**Prioridad:** alta antes de reporting avanzado.

**P2-01 — CLOSED (2026-09-06, commit pendiente: `fix: harden tenant query builder`).**
El builder instala una sola condición tenant, conserva sus bindings y rechaza cambios de contexto con `TenantContextChanged`. Las condiciones con `OR` se agrupan dentro del límite tenant, también para lecturas, updates y deletes del builder base. `TenantQueryBuilderHardeningTest` verifica reutilización, SQL/bindings estables, unions compatibles e incompatibles, clones y subconsultas en MySQL.

## P2-02 — Aliased root table

`Customer::query()->from('customers as c')` produce un conflicto entre `customers.company_id` y `c.company_id`. El comportamiento es fail-closed y no existe un tenant leak confirmado.

**Prioridad:** corregir antes de reporting o subqueries complejas.

**P2-02 — CLOSED (2026-09-06, commit pendiente: `fix: harden tenant query builder`).**
`TenantTableReference` centraliza la referencia efectiva de la raíz; `TenantScope` delega en la misma frontera del builder base. Regresiones MySQL cubren `as`/`AS`, filtros, orden, agregados y join representativo. La garantía corresponde al modelo raíz; las tablas joined requieren sus propias restricciones de dominio.

Validación del cierre: hardening **13 tests / 47 assertions**, tenancy **128 / 296**, suite completa **160 / 355**, todos PASS. Los P3 siguientes permanecen **OPEN**.

## P3-01 — TransferAuthorization

Actualmente registra intención, pero todavía no está enlazado a autorización mediante Gate/User. No existe un endpoint público. Antes de exponer una superficie administrativa de transferencia de ownership debe añadirse autorización explícita.

## P3-02 — Membership raw query boundary

`Membership` deliberadamente no usa `BelongsToTenant`. Los callers conocidos filtran correctamente por company. Mantener esta frontera documentada y revisar futuros callers.

## P3-03 — Public tenant-owned routes

El binding tenant-aware para guests devuelve `401`, que es correcto para las rutas existentes. Una futura cotización pública por token requerirá un mecanismo específico, explícito y separado.
