import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Field from '../../components/Field';
import Layout from '../../components/Layout';
import type { Currency, Product, ProductFields, ProductUnit } from '../../types';

export default function ProductForm({ product, units }: { product: Product | null; units: { value: ProductUnit; label: string }[] }) {
    const form = useForm<ProductFields>({
        sku: product?.sku ?? '', name: product?.name ?? '', description: product?.description ?? '', category: product?.category ?? '',
        unit: product?.unit ?? 'unit', base_cost: product?.base_cost ?? '', base_price: product?.base_price ?? '', currency: product?.currency ?? 'USD',
    });
    const title = product ? 'Editar producto' : 'Agregar producto';
    function submit(e: FormEvent) {
        e.preventDefault();
        const options = { onError: () => requestAnimationFrame(() => document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()) };
        if (product) form.put('/products/' + product.id, options); else form.post('/products', options);
    }
    const field = (key: keyof ProductFields, label: string, maxLength: number, extra = {}) => <Field id={key} name={key} label={label} value={form.data[key]} onChange={e => form.setData(key, e.target.value as never)} error={form.errors[key]} maxLength={maxLength} {...extra} />;
    return <Layout title={title}>
        <Link className="back-link" href={product ? '/products/' + product.id : '/products'}>Volver a {product ? 'la ficha' : 'productos'}</Link>
        <div className="page-heading"><div><h1>{title}</h1><p className="muted">Define lo necesario para reconocerlo y cotizarlo.</p></div></div>
        <form onSubmit={submit} className="customer-form">
            <section className="form-section"><div><h2>Producto</h2><p>Identificación rápida dentro del catálogo.</p></div><div className="fields-grid">
                {field('name', 'Nombre del producto', 255, { required: true, autoFocus: true })}{field('sku', 'SKU', 100, { required: true })}{field('category', 'Categoría', 120)}
                <div className="field"><label htmlFor="unit">Unidad *</label><select id="unit" className="input" required value={form.data.unit} onChange={e => form.setData('unit', e.target.value as ProductUnit)}>{units.map(unit => <option key={unit.value} value={unit.value}>{unit.label}</option>)}</select>{form.errors.unit && <p className="field-error">{form.errors.unit}</p>}</div>
            </div></section>
            <section className="form-section"><div><h2>Precio</h2><p>Importes exactos; la aplicación no convierte monedas.</p></div><div className="fields-grid">
                {field('base_price', 'Precio base', 22, { required: true, inputMode: 'decimal', placeholder: '0.00' })}{field('base_cost', 'Costo base', 22, { inputMode: 'decimal', placeholder: 'Opcional' })}
                <div className="field"><label htmlFor="currency">Moneda *</label><select id="currency" className="input" required value={form.data.currency} onChange={e => form.setData('currency', e.target.value as Currency)}>{(['USD', 'BOB', 'ARS'] as Currency[]).map(currency => <option key={currency}>{currency}</option>)}</select>{form.errors.currency && <p className="field-error">{form.errors.currency}</p>}</div>
            </div></section>
            <section className="form-section"><div><h2>Información adicional</h2><p>Contexto útil al buscar y seleccionar.</p></div><div className="fields-stack">{field('description', 'Descripción', 5000, { multiline: true })}</div></section>
            <div className="form-actions"><Link className="button" href={product ? '/products/' + product.id : '/products'}>Cancelar</Link><button className="button primary" disabled={form.processing}>{form.processing ? 'Guardando…' : product ? 'Guardar cambios' : 'Guardar producto'}</button></div>
        </form>
    </Layout>;
}
