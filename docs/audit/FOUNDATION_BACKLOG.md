# Foundation backlog

Estos findings no bloquean el baseline aprobado, pero deben resolverse antes de ampliar las superficies indicadas.

## P2-01 — Tenant predicate duplication

`TenantQueryBuilder::applyBeforeQueryCallbacks()` puede añadir repetidamente `company_id = ?` al reutilizar builders. Se observaron SQL redundante, bindings acumulados y `union()` roto. El comportamiento es fail-closed y no existe un tenant leak confirmado.

**Prioridad:** alta antes de reporting avanzado.

## P2-02 — Aliased root table

`Customer::query()->from('customers as c')` produce un conflicto entre `customers.company_id` y `c.company_id`. El comportamiento es fail-closed y no existe un tenant leak confirmado.

**Prioridad:** corregir antes de reporting o subqueries complejas.

## P3-01 — TransferAuthorization

Actualmente registra intención, pero todavía no está enlazado a autorización mediante Gate/User. No existe un endpoint público. Antes de exponer una superficie administrativa de transferencia de ownership debe añadirse autorización explícita.

## P3-02 — Membership raw query boundary

`Membership` deliberadamente no usa `BelongsToTenant`. Los callers conocidos filtran correctamente por company. Mantener esta frontera documentada y revisar futuros callers.

## P3-03 — Public tenant-owned routes

El binding tenant-aware para guests devuelve `401`, que es correcto para las rutas existentes. Una futura cotización pública por token requerirá un mecanismo específico, explícito y separado.
