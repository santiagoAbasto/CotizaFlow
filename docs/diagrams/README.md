# Diagramas verificables

Este directorio conserva mapas compactos de CotizaFlow para comprender el sistema sin releer todo el repositorio. Archify está versionado en `.agents/skills/archify` y Claude Code accede a la misma copia desde `.claude/skills/archify`.

## Qué guardar

Cada diagrama debe incluir:

- la fuente tipada `<nombre>.<tipo>.json`;
- el artefacto validado `<nombre>.html`;
- hechos respaldados por código o documentación vigente;
- un alcance acotado y, cuando corresponda, trust boundaries.

Tipos disponibles: `architecture`, `workflow`, `sequence`, `dataflow` y `lifecycle`.

## Mapas actuales

- [Runtime architecture — fuente](runtime-architecture.architecture.json) · [artefacto HTML](runtime-architecture.html): request autenticado, frontera tenant, dominio comercial, MySQL y capacidad de jobs tenant-aware.
- [Quote status lifecycle — fuente](quote-status.lifecycle.json) · [artefacto HTML](quote-status-lifecycle.html): emisión manual, estados terminales y expiración diaria de cotizaciones del Sprint 0.
- [Follow-up lifecycle — fuente](follow-up.lifecycle.json) · [artefacto HTML](follow-up.lifecycle.html): programación, resultado, próximo contacto y cierre automático al aceptar/rechazar una quote.
- [Dashboard de acción comercial — fuente](dashboard-action-flow.workflow.json) · [artefacto HTML](dashboard-action-flow.html): señales operativas, agrupación monetaria por moneda y navegación desde prioridad a cotización.

## Uso

Desde la raíz del repositorio:

```bash
node .agents/skills/archify/bin/archify.mjs validate <tipo> docs/diagrams/<fuente>.json --quality showcase --repo-root . --json
node .agents/skills/archify/bin/archify.mjs deliver <tipo> docs/diagrams/<fuente>.json docs/diagrams/<salida>.html --quality showcase --repo-root . --json
```

Seguir siempre las instrucciones de la skill. No crear topología imaginada: inspeccionar primero el código y los documentos relacionados. Cuando cambie una relación, frontera o componente representado, actualizar la fuente y volver a validar el artefacto.

## Convenciones

- Preferir 8–12 componentes principales y una ruta central clara.
- Mantener nombres reales de módulos, servicios, protocolos y límites.
- No incluir secretos, credenciales, datos personales ni URLs privadas.
- Tratar el código y los documentos arquitectónicos como fuente de verdad.
- Registrar decisiones duraderas en `docs/adr/`, no únicamente en un diagrama.
