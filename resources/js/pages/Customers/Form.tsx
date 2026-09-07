import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Layout from '../../components/Layout';
import Field from '../../components/Field';
import type { Customer, CustomerFields } from '../../types';
export default function CustomerForm({ customer }: { customer: Customer | null }) {
    const form = useForm<CustomerFields>({
        name: customer?.name ?? '', legal_name: customer?.legal_name ?? '', tax_id: customer?.tax_id ?? '',
        email: customer?.email ?? '', phone: customer?.phone ?? '', whatsapp: customer?.whatsapp ?? '',
        commercial_condition: customer?.commercial_condition ?? '', notes: customer?.notes ?? '',
    });
    const title = customer ? 'Editar cliente' : 'Agregar cliente';
    function submit(e: FormEvent) {
        e.preventDefault();
        const options = { onError: () => requestAnimationFrame(() => document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()) };
        if (customer) form.put('/customers/' + customer.id, options); else form.post('/customers', options);
    }
    const field = (key: keyof CustomerFields, label: string, maxLength: number, extra = {}) => <Field id={key} name={key} label={label} value={form.data[key]} onChange={e => form.setData(key, e.target.value)} error={form.errors[key]} maxLength={maxLength} {...extra} />;
    return <Layout title={title}>
        <Link className="back-link" href={customer ? '/customers/' + customer.id : '/customers'}>Volver a {customer ? 'la ficha' : 'clientes'}</Link>
        <div className="page-heading"><div><h1>{title}</h1><p className="muted">Solo el nombre es obligatorio. Completa el resto cuando lo necesites.</p></div></div>
        <form onSubmit={submit} className="customer-form">
            <section className="form-section"><div><h2>Información principal</h2><p>Cómo reconocerás a este cliente.</p></div><div className="fields-grid">
                {field('name', 'Nombre del cliente', 255, { required: true, autoFocus: true, autoComplete: 'organization' })}
                {field('legal_name', 'Razón social', 255)}
                {field('tax_id', 'Identificación fiscal', 80)}
            </div></section>
            <section className="form-section"><div><h2>Contacto</h2><p>Datos para conversar y enviar cotizaciones.</p></div><div className="fields-grid">
                {field('email', 'Email', 255, { type: 'email', autoComplete: 'email' })}
                {field('phone', 'Teléfono', 60, { type: 'tel', autoComplete: 'tel' })}
                {field('whatsapp', 'WhatsApp', 60, { type: 'tel' })}
            </div></section>
            <section className="form-section"><div><h2>Relación comercial</h2><p>Condiciones y contexto para tu equipo.</p></div><div className="fields-stack">
                {field('commercial_condition', 'Condición comercial', 500, { placeholder: 'Ej.: contado o pago a 30 días' })}
                {field('notes', 'Notas internas', 5000, { multiline: true })}
            </div></section>
            <div className="form-actions"><Link className="button" href={customer ? '/customers/' + customer.id : '/customers'}>Cancelar</Link><button className="button primary" disabled={form.processing}>{form.processing ? 'Guardando…' : customer ? 'Guardar cambios' : 'Guardar cliente'}</button></div>
        </form>
    </Layout>;
}
