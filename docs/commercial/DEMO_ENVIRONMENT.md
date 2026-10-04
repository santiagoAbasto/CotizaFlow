# Entorno demo sintético

La demo usa únicamente datos sintéticos de **Distribuidora Industrial Andina**. No cargar información real de prospectos o clientes en este entorno.

## Preparación

El comando es deliberadamente estricto:

```bash
php artisan demo:commercial --force
```

Sólo funciona en `local` o `testing`, con `DB_URL` vacío y contra `cotizaflow_demo` o `cotizaflow_testing`. Reemplaza exclusivamente el tenant sintético `distribuidora-industrial-andina-demo`; no ejecuta migraciones ni borra bases de datos.

El acceso resultante es sintético y sólo apto para demo local:

- Email: `demo@cotizaflow.test`
- Contraseña: `CotizaFlowDemo2026!`

El escenario deja tres cotizaciones enviadas: una con seguimiento vencido, una para hoy y una sin seguimiento. Esto permite demostrar la prioridad comercial del dashboard sin inventar datos de clientes reales.
