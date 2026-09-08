import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Layout from '../../components/Layout';
import Status from '../../components/Status';
import type { PriceList } from '../../types';

type Props = {
    priceLists: {
        data: PriceList[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { q: string; status: string };
};
export default function Index({ priceLists, filters }: Props) {
    const [q, setQ] = useState(filters.q);
    const [status, setStatus] = useState(filters.status);
    const [loading, setLoading] = useState(false);
    function search(e: FormEvent) {
        e.preventDefault();
        router.get(
            '/price-lists',
            { q, status },
            { preserveState: true, onStart: () => setLoading(true), onFinish: () => setLoading(false) },
        );
    }
    return (
        <Layout title="Listas de precios">
            <div className="page-heading">
                <div>
                    <p className="eyebrow">Política comercial</p>
                    <h1>
                        Listas de precios <span className="count">{priceLists.total}</span>
                    </h1>
                    <p className="muted">Define precios específicos sin alterar el precio base.</p>
                </div>
                <Link href="/price-lists/create" className="button primary">
                    Crear lista
                </Link>
            </div>
            <form onSubmit={search} className="filters" aria-label="Buscar listas de precios">
                <div className="search-field">
                    <label htmlFor="q">Buscar lista</label>
                    <input
                        id="q"
                        type="search"
                        className="input"
                        placeholder="Nombre de la lista"
                        maxLength={100}
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                </div>
                <div>
                    <label htmlFor="status">Estado</label>
                    <select id="status" className="input" value={status} onChange={(e) => setStatus(e.target.value)}>
                        <option value="">Todos los estados</option>
                        <option value="active">Activas</option>
                        <option value="inactive">Inactivas</option>
                    </select>
                </div>
                <button className="button" disabled={loading}>
                    {loading ? 'Buscando…' : 'Buscar'}
                </button>
            </form>
            {priceLists.data.length === 0 ? (
                <div className="empty-state">
                    <h2>
                        {filters.q || filters.status
                            ? 'No encontramos listas con esos filtros.'
                            : 'Todavía no tienes listas de precios.'}
                    </h2>
                    <p>
                        {filters.q || filters.status
                            ? 'Prueba otro nombre o estado.'
                            : 'Crea una lista para asignar precios comerciales a tus productos.'}
                    </p>
                    {!filters.q && !filters.status && (
                        <Link href="/price-lists/create" className="button primary">
                            Crear lista
                        </Link>
                    )}
                </div>
            ) : (
                <div className="simple-table">
                    <div className="simple-head">
                        <span>Lista</span>
                        <span>Moneda</span>
                        <span>Precios asignados</span>
                        <span>Estado</span>
                    </div>
                    {priceLists.data.map((list) => (
                        <article className="simple-row" key={list.id}>
                            <Link href={'/price-lists/' + list.id} className="customer-name">
                                {list.name}
                            </Link>
                            <span>{list.currency}</span>
                            <span>{list.prices_count ?? 0}</span>
                            <Status status={list.status} />
                        </article>
                    ))}
                </div>
            )}
            {priceLists.total > 0 && (
                <nav className="pagination" aria-label="Paginación">
                    <p>
                        Página {priceLists.current_page} de {priceLists.last_page} · {priceLists.total} listas
                    </p>
                    <div>
                        {priceLists.prev_page_url ? (
                            <Link className="button" href={priceLists.prev_page_url}>
                                Anterior
                            </Link>
                        ) : (
                            <button className="button" disabled>
                                Anterior
                            </button>
                        )}
                        {priceLists.next_page_url ? (
                            <Link className="button" href={priceLists.next_page_url}>
                                Siguiente
                            </Link>
                        ) : (
                            <button className="button" disabled>
                                Siguiente
                            </button>
                        )}
                    </div>
                </nav>
            )}
        </Layout>
    );
}
