import { Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Layout from '../../components/Layout';
import Status from '../../components/Status';
import type { PriceList, Product, ResolvedProductPrice } from '../../types';

type Override = { price_list_id: number; price_list_name: string; status: 'active' | 'inactive'; price: string; display_price: string };
export default function Show({ product, priceLists, selectedPriceList, resolvedPrice, overrides }: { product: Product; priceLists: PriceList[]; selectedPriceList: number | null; resolvedPrice: ResolvedProductPrice; overrides: Override[] }) {
    const statusForm = useForm({ status: product.status === 'active' ? 'inactive' : 'active' });
    const [list, setList] = useState(selectedPriceList?.toString() ?? '');
    function consult(e: FormEvent) { e.preventDefault(); router.get('/products/' + product.id, { price_list: list || undefined }, { preserveState: true }); }
    function toggle() { statusForm.transform(() => ({ status: product.status === 'active' ? 'inactive' : 'active' })); statusForm.patch('/products/' + product.id + '/status', { preserveScroll: true }); }
    return <Layout title={product.name}>
        <Link href="/products" className="back-link">Volver a productos</Link>
        <div className="page-heading"><div><p className="eyebrow">{product.sku}</p><h1>{product.name}</h1>{product.category && <p className="muted">{product.category}</p>}<Status status={product.status} /></div><Link className="button primary" href={'/products/' + product.id + '/edit'}>Editar producto</Link></div>
        {product.status === 'inactive' && <p className="notice">Este producto está inactivo y no estará disponible para nuevas cotizaciones.</p>}
        <section className="price-resolver"><div><p className="eyebrow">Precio comercial resultante</p><p className="resolved-money">{resolvedPrice.currency} {resolvedPrice.display_price}</p><p className="muted">Origen: {resolvedPrice.source === 'price_list' ? resolvedPrice.price_list_name : 'Precio base'}.</p></div>
            <form onSubmit={consult}><label htmlFor="price_list">Consultar lista</label><div className="inline-controls"><select id="price_list" className="input" value={list} onChange={e => setList(e.target.value)}><option value="">Sin lista · precio base</option>{priceLists.map(item => <option key={item.id} value={item.id}>{item.name} · {item.currency}</option>)}</select><button className="button">Consultar</button></div></form>
        </section>
        <div className="detail-grid product-detail"><section><h2>Producto</h2><dl><dt>SKU</dt><dd>{product.sku}</dd><dt>Categoría</dt><dd>{product.category || 'Sin categoría'}</dd><dt>Unidad</dt><dd>{product.unit_label}</dd><dt>Descripción</dt><dd className="preserve-text">{product.description || 'Sin descripción.'}</dd></dl></section>
            <section><h2>Precio base</h2><dl><dt>Precio</dt><dd className="money strong">{product.currency} {product.display_base_price}</dd><dt>Costo</dt><dd>{product.display_base_cost === null ? 'Sin registrar' : product.currency + ' ' + product.display_base_cost}</dd><dt>Moneda</dt><dd>{product.currency}</dd></dl></section></div>
        <section className="future-history"><div className="section-heading"><div><h2>Precios por lista</h2><p>Los overrides reemplazan el precio base solo para la lista indicada.</p></div><Link href="/price-lists" className="text-button">Gestionar listas</Link></div>
            {overrides.length === 0 ? <p className="muted">Este producto todavía no tiene precios específicos por lista.</p> : <div className="compact-list">{overrides.map(item => <div key={item.price_list_id}><Link href={'/price-lists/' + item.price_list_id}>{item.price_list_name}</Link><span>{product.currency} {item.display_price}</span><Status status={item.status} /></div>)}</div>}
        </section>
        <section className="status-action"><div><h2>Estado del producto</h2><p>{product.status === 'active' ? 'Desactívalo sin borrar su información ni sus precios.' : 'Actívalo para volver a seleccionarlo en nuevas cotizaciones.'}</p></div><button className="button" disabled={statusForm.processing} onClick={toggle}>{statusForm.processing ? 'Actualizando…' : product.status === 'active' ? 'Desactivar producto' : 'Activar producto'}</button></section>
    </Layout>;
}
