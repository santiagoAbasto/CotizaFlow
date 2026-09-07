import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Field from '../../components/Field';
import Layout from '../../components/Layout';
import type { Currency, PriceList, PriceListFields } from '../../types';

export default function PriceListForm({ priceList }: { priceList: PriceList | null }) {
    const form = useForm<PriceListFields>({ name: priceList?.name ?? '', currency: priceList?.currency ?? 'USD' });
    const title = priceList ? 'Editar lista de precios' : 'Crear lista de precios';
    function submit(e: FormEvent) { e.preventDefault(); if (priceList) form.put('/price-lists/' + priceList.id); else form.post('/price-lists'); }
    return <Layout title={title}><Link className="back-link" href={priceList ? '/price-lists/' + priceList.id : '/price-lists'}>Volver a {priceList ? 'la lista' : 'listas'}</Link><div className="page-heading"><div><h1>{title}</h1><p className="muted">Una lista agrupa overrides en una sola moneda.</p></div></div>
        <form className="narrow-form" onSubmit={submit}><Field id="name" name="name" label="Nombre de la lista" required autoFocus maxLength={255} value={form.data.name} onChange={e => form.setData('name', e.target.value)} error={form.errors.name} /><div className="field"><label htmlFor="currency">Moneda *</label><select id="currency" className="input" required value={form.data.currency} onChange={e => form.setData('currency', e.target.value as Currency)}>{(['USD', 'BOB', 'ARS'] as Currency[]).map(currency => <option key={currency}>{currency}</option>)}</select>{form.errors.currency && <p className="field-error">{form.errors.currency}</p>}</div><p className="form-help">Solo podrás asignar productos que usen esta misma moneda. Sin conversión automática.</p><div className="form-actions"><Link className="button" href={priceList ? '/price-lists/' + priceList.id : '/price-lists'}>Cancelar</Link><button className="button primary" disabled={form.processing}>{form.processing ? 'Guardando…' : priceList ? 'Guardar cambios' : 'Crear lista'}</button></div></form>
    </Layout>;
}
