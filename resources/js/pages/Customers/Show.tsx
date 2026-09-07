import { Link, useForm } from '@inertiajs/react';
import Layout from '../../components/Layout';
import Status from '../../components/Status';
import type { Customer, CustomerStatus } from '../../types';
export default function Show({ customer: c }: { customer: Customer }) {
    const form = useForm<{ status: CustomerStatus }>({ status: c.status === 'active' ? 'inactive' : 'active' });
    function toggle() {
        form.transform(() => ({ status: c.status === 'active' ? 'inactive' : 'active' }));
        form.patch('/customers/' + c.id + '/status', { preserveScroll: true });
    }
    return <Layout title={c.name}>
        <Link href="/customers" className="back-link">Volver a clientes</Link>
        <div className="page-heading"><div><p className="eyebrow">Ficha de cliente</p><h1>{c.name}</h1>{c.legal_name && <p className="muted">{c.legal_name}</p>}<Status status={c.status} /></div><Link className="button primary" href={'/customers/' + c.id + '/edit'}>Editar cliente</Link></div>
        {c.status === 'inactive' && <p className="notice">Este cliente está inactivo. Su información permanece disponible.</p>}
        <div className="detail-grid">
            <section><h2>Datos comerciales</h2><dl>
                <dt>Identificación fiscal</dt><dd>{c.tax_id || 'Sin registrar'}</dd>
                <dt>Condición comercial</dt><dd>{c.commercial_condition || 'Sin definir'}</dd>
                <dt>Notas internas</dt><dd className="preserve-text">{c.notes || 'No hay notas registradas.'}</dd>
            </dl></section>
            <section><h2>Contacto</h2><dl>
                <dt>Email</dt><dd>{c.email || 'Sin registrar'}</dd>
                <dt>Teléfono</dt><dd>{c.phone || 'Sin registrar'}</dd>
                <dt>WhatsApp</dt><dd>{c.whatsapp || 'Sin registrar'}</dd>
            </dl></section>
        </div>
        <section className="future-history"><h2>Cotizaciones</h2><p>Las cotizaciones de este cliente aparecerán aquí.</p></section>
        <section className="status-action"><div><h2>Estado del cliente</h2><p>{c.status === 'active' ? 'Desactiva el cliente sin perder su información comercial.' : 'Activa el cliente para volver a trabajar con él.'}</p></div><button className="button" disabled={form.processing} onClick={toggle}>{form.processing ? 'Actualizando…' : c.status === 'active' ? 'Desactivar cliente' : 'Activar cliente'}</button>{form.errors.status && <p role="alert">{form.errors.status}</p>}</section>
    </Layout>;
}
