# Feature Development

El flujo obligatorio es:

**ANALYZE → PLAN → IMPLEMENT → TEST → REVIEW → REPORT**

## ANALYZE

Leer `AGENTS.md`, documentación, código y tests relacionados. Identificar valor comercial, usuarios, tenant boundary, autorización, invariantes financieras, datos históricos e integraciones.

Para features grandes documentar antes de programar:

- `CURRENT STATE`
- `GAP`
- `RISKS`
- `IMPLEMENTATION PLAN`
- `FILES`

## PLAN e IMPLEMENT

Dividir el cambio en una unidad pequeña y verificable. Mantener controllers delgados, dominio determinístico, UI sin autoridad de negocio e integraciones desacopladas. Añadir migrations, índices, constraints, auditoría y observabilidad cuando sean necesarios.

## TEST

Crear o actualizar tests proporcionales al riesgo. Los flujos financieros, tenant boundaries, permisos, imports, APIs, webhooks y fallback de IA requieren cobertura explícita. No eliminar ni debilitar tests para lograr verde.

## REVIEW

Revisar el diff completo, seguridad, tenant isolation, consultas, concurrencia, estados de error, accesibilidad, UX y compatibilidad de migrations. Ejecutar las validaciones relevantes.

## REPORT

Al terminar informar:

- `IMPLEMENTED`
- `TEST RESULTS` con comandos y resultado real
- `SECURITY REVIEW`
- `TECHNICAL DEBT`
- `NEXT BEST STEP`

No declarar una prueba, build o validación como exitosa si no fue ejecutada.
