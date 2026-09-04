# Arquitectura de IA

## Contratos

`AIProviderInterface` define las capacidades independientes del proveedor. `AIOrchestrator` selecciona proveedores, aplica configuración, versiona prompts, ejecuta fallbacks y centraliza observabilidad, límites y manejo de errores.

La lógica de dominio depende de contratos propios, nunca de SDKs de OpenAI, Anthropic, Gemini u otro proveedor. Cada integración se implementa como adapter reemplazable.

## Responsabilidad permitida

La IA puede interpretar texto, extraer entidades, clasificar, sugerir product matching, resumir contexto, detectar intención, priorizar de forma explicable, redactar respuestas y recomendar una siguiente acción.

La IA nunca es autoridad sobre precio, costo real, descuento máximo, margen, impuestos, stock, saldo ni total financiero. Sus salidas se validan, se tratan como no confiables y pasan al dominio o a revisión humana.

## Trazabilidad

Registrar cuando corresponda, respetando privacidad y logging seguro:

- provider y model;
- prompt version;
- tokens de entrada y salida;
- latency;
- status;
- error normalizado;
- tenant, caso de uso y correlation ID;
- costo estimado y fallback utilizado.

No registrar secretos ni contenido sensible completo por defecto. Establecer timeouts, retries acotados, circuit breaking o fallback cuando el riesgo operativo lo justifique. Tests deben cubrir proveedor exitoso, salida inválida, timeout, error, fallback y límites por tenant.
