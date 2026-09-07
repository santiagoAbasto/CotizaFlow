import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Layout from '../../components/Layout';
import Status from '../../components/Status';
import type { Product } from '../../types';

type Props = { products: { data: Product[]; total: number; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null }; filters: { q: string; status: string } };

export default function Index({ products, filters }: Props) {
    const [q, setQ] = useState(filters.q);
    const [status, setStatus] = useState(filters.status);
    const [loading, setLoading] = useState(false);
    function search(e: FormEvent) {
        e.preventDefault();
        router.get('/products', { q, status }, { preserveState: true, onStart: () => setLoading(true), onFinish: () => setLoading(false) });
    }
    return <Layout title="Productos">
        <div className="page-heading"><div><p className="eyebrow">Catálogo comercial</p><h1>Productos <span className="count">{products.total}</span></h1><p className="muted">Encuentra el producto y su precio antes de cotizar.</p></div><Link href="/products/create" className="button primary">Agregar producto</Link></div>
        <form onSubmit={search} className="filters" aria-label="Buscar productos">
            <div className="search-field"><label htmlFor="q">Buscar producto</label><input id="q" type="search" className="input" placeholder="SKU, nombre, descripción o categoría" value={q} maxLength={100} onChange={e => setQ(e.target.value)} /></div>
            <div><label htmlFor="status">Estado</label><select id="status" className="input" value={status} onChange={e => setStatus(e.target.value)}><option value="">Todos los estados</option><option value="active">Activos</option><option value="inactive">Inactivos</option></select></div>
            <button className="button" disabled={loading}>{loading ? 'Buscando…' : 'Buscar'}</button>
            {(filters.q || filters.status) && <Link href="/products" className="text-button">Limpiar filtros</Link>}
        </form>
        <section aria-label="Catálogo de productos" aria-busy={loading}>
            {products.data.length === 0 ? <div className="empty-state"><h2>{filters.q || filters.status ? 'No encontramos productos con esos filtros.' : 'Todavía no tienes productos.'}</h2><p>{filters.q || filters.status ? 'Prueba otro SKU, nombre o estado.' : 'Agrega tu primer producto para comenzar a crear cotizaciones.'}</p>{!filters.q && !filters.status && <Link href="/products/create" className="button primary">Agregar producto</Link>}</div> :
                <div className="product-table"><div className="product-head"><span>SKU / Producto</span><span>Categoría</span><span>Unidad</span><span>Costo base</span><span>Precio base</span><span>Estado</span></div>
                    {products.data.map(product => <article className="product-row" key={product.id}>
                        <div><span className="sku">{product.sku}</span><Link href={'/products/' + product.id} className="customer-name">{product.name}</Link></div>
                        <div>{product.category || <span className="muted">Sin categoría</span>}</div><div>{product.unit_label}</div>
                        <div className="money">{product.display_base_cost === null ? <span className="muted">Sin registrar</span> : product.currency + ' ' + product.display_base_cost}</div>
                        <div className="money strong">{product.currency} {product.display_base_price}</div><div><Status status={product.status} /></div>
                    </article>)}
                </div>}
            {products.total > 0 && <nav className="pagination" aria-label="Paginación"><p>Página {products.current_page} de {products.last_page} · {products.total} productos</p><div>{products.prev_page_url ? <Link className="button" href={products.prev_page_url}>Anterior</Link> : <button className="button" disabled>Anterior</button>}{products.next_page_url ? <Link className="button" href={products.next_page_url}>Siguiente</Link> : <button className="button" disabled>Siguiente</button>}</div></nav>}
        </section>
    </Layout>;
}
