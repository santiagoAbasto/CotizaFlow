# Grafo persistente del codebase

Graphify mantiene una representación consultable del código y la documentación entre sesiones. Su objetivo es reducir relecturas amplias del repositorio, no reemplazar la verificación del código fuente.

## Instalación del proyecto

- Skill canónica: `.agents/skills/graphify`.
- Claude Code: `.claude/skills/graphify`, enlace a la copia canónica.
- Ejecutable requerido: `graphify` (paquete Python `graphifyy`).
- Salida persistente: `graphify-out/`.

## Política de uso

1. Si existe `graphify-out/graph.json`, comenzar las preguntas sobre arquitectura o relaciones con `graphify query`.
2. Limitar la respuesta con `--budget` para cargar solo el contexto necesario.
3. Confirmar en código fuente los hechos críticos, financieros, de seguridad o multi-tenancy.
4. Ejecutar una actualización incremental después de cambios estructurales relevantes.
5. No reconstruir el grafo completo por cada cambio pequeño.

## Comandos

```bash
graphify query "¿Cómo fluye una cotización?" --budget 1500
graphify path "Quote" "PricingEngine"
graphify explain "TenantContext"
graphify . --update
```

La creación inicial del grafo se ejecuta mediante la skill `graphify` sobre la raíz del proyecto. Puede requerir extracción semántica y consumir tokens una sola vez; las consultas posteriores y las actualizaciones incrementales reutilizan la salida y su cache.

## Artefactos esperados

- `graphify-out/graph.json`: grafo consultable.
- `graphify-out/GRAPH_REPORT.md`: reporte y auditoría.
- `graphify-out/graph.html`: explorador interactivo.
- `graphify-out/cost.json`: historial de consumo.

No incluir secretos ni datos sensibles en el corpus. El grafo es una ayuda de navegación: el código, los tests, los ADRs y la documentación arquitectónica conservan la autoridad.
