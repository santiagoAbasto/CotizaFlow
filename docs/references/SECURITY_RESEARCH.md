# Security Research

## Fuente curada

[Awesome Annual Security Reports](https://github.com/jacobdjwilson/awesome-annual-security-reports) es una biblioteca de investigación para identificar tendencias, amenazas y preguntas de revisión. No es una dependencia de Laravel/React, un linter ni una fuente de controles implementables por sí sola. No clonar sus PDFs ni incorporar informes al repositorio salvo que una decisión concreta requiera una evidencia mínima y enlazada.

## Cuándo consultarla

Considerarla al diseñar o revisar autenticación, sesiones, MFA, recuperación de cuenta, autorización/RBAC, aislamiento tenant, uploads, documentos, almacenamiento, APIs, webhooks, tokens, secretos, OAuth, integraciones, pagos, PII, logs, backups, infraestructura, dependencias o IA/prompt injection.

## Orden de evidencia

Antes de usar un informe anual para decidir un control, priorizar: documentación oficial del framework/proveedor, OWASP, NIST, RFC aplicable y CWE/CVE. Los informes dan contexto e inteligencia de amenazas; no sustituyen estándares técnicos ni pruebas del comportamiento real.

## Registro de evidencia

Cuando un informe influya una decisión, registrarlo en el ADR, la documentación de seguridad o la revisión correspondiente con:

- organización autora y año;
- fecha de publicación/consulta;
- título y enlace original directo;
- hallazgo relevante resumido, sin copiar el informe;
- control concreto adoptado o motivo para no adoptarlo;
- estándar o fuente primaria que respalda la implementación.

No registrar tokens, payloads sensibles, PII ni copias locales innecesarias. La evidencia debe seguir siendo verificable por enlace y conservar provenance.

## Seguimiento de tooling

Larastan/PHPStan se ejecuta actualmente en nivel 1 sin baseline ni ignores. El repositorio tiene diagnósticos reales al subir a nivel 2, principalmente porque los casts y relaciones Eloquent todavía no están declarados para análisis estático. Antes de elevar ese nivel, corregir los contratos de modelos y builders; no ocultar hallazgos mediante un baseline.
