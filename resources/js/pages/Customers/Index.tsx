import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Layout from '../../components/Layout';
import Status from '../../components/Status';
import type { Customer } from '../../types';
type Props = {
    customers: {
        data: Customer[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { q: string; status: string };
};
export default function Index({ customers, filters }: Props) {
    const [q, setQ] = useState(filters.q);
    const [status, setStatus] = useState(filters.status);
    const [loading, setLoading] = useState(false);
    function search(e: FormEvent) {
        e.preventDefault();
        router.get(
            '/customers',
            { q, status },
            { preserveState: true, onStart: () => setLoading(true), onFinish: () => setLoading(false) },
        );
    }
    return (
        <Layout title="Clientes">
            <div className="page-heading">
                <div>
                    <p className="eyebrow">Directorio comercial</p>
                    <h1>
                        Clientes <span className="count">{customers.total}</span>
                    </h1>
                    <p className="muted">Encuentra a quién vas a cotizar.</p>
                </div>
                <Link href="/customers/create" className="button primary">
                    Agregar cliente
                </Link>
            </div>
            <form onSubmit={search} className="filters" aria-label="Buscar clientes">
                <div className="search-field">
                    <label htmlFor="q">Buscar cliente</label>
                    <input
                        id="q"
                        type="search"
                        className="input"
                        placeholder="Nombre, razón social, identificación o contacto"
                        value={q}
                        maxLength={100}
                        onChange={(e) => setQ(e.target.value)}
                    />
                </div>
                <div>
                    <label htmlFor="status">Estado</label>
                    <select id="status" className="input" value={status} onChange={(e) => setStatus(e.target.value)}>
                        <option value="">Todos los estados</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Inactivos</option>
                    </select>
                </div>
                <button className="button" disabled={loading}>
                    {loading ? 'Buscando…' : 'Buscar'}
                </button>
                {(filters.q || filters.status) && (
                    <Link
                        href="/customers"
                        onClick={() => {
                            setQ('');
                            setStatus('');
                        }}
                        className="text-button"
                    >
                        Limpiar filtros
                    </Link>
                )}
            </form>
            <section aria-label="Directorio de clientes" aria-busy={loading}>
                {customers.data.length === 0 ? (
                    <div className="empty-state">
                        <h2>
                            {filters.q || filters.status
                                ? 'No encontramos clientes con esos filtros.'
                                : 'Todavía no tienes clientes.'}
                        </h2>
                        <p>
                            {filters.q || filters.status
                                ? 'Prueba otro nombre o cambia el estado.'
                                : 'Agrega tu primer cliente para comenzar a crear cotizaciones.'}
                        </p>
                        {!filters.q && !filters.status && (
                            <Link href="/customers/create" className="button primary">
                                Agregar cliente
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="customer-table">
                        <div className="table-head">
                            <span>Cliente</span>
                            <span>Contacto</span>
                            <span>Condición comercial</span>
                            <span>Estado</span>
                            <span>Actualizado</span>
                        </div>
                        {customers.data.map((c) => (
                            <article key={c.id} className="customer-row">
                                <div>
                                    <Link href={'/customers/' + c.id} className="customer-name">
                                        {c.name}
                                    </Link>
                                    {c.legal_name && <p>{c.legal_name}</p>}
                                    {c.tax_id && <small>ID fiscal: {c.tax_id}</small>}
                                </div>
                                <div>
                                    {c.email && <p>{c.email}</p>}
                                    {(c.phone || c.whatsapp) && (
                                        <p>
                                            {c.phone || c.whatsapp}
                                            {!c.phone && c.whatsapp ? ' · WhatsApp' : ''}
                                        </p>
                                    )}
                                    {!c.email && !c.phone && !c.whatsapp && (
                                        <span className="muted">Sin contacto registrado</span>
                                    )}
                                </div>
                                <div className="commercial">
                                    {c.commercial_condition || <span className="muted">Sin definir</span>}
                                </div>
                                <div>
                                    <Status status={c.status} />
                                </div>
                                <time dateTime={c.updated_at}>
                                    {new Date(c.updated_at).toLocaleDateString('es', {
                                        day: '2-digit',
                                        month: 'short',
                                        year: 'numeric',
                                    })}
                                </time>
                            </article>
                        ))}
                    </div>
                )}
                {customers.total > 0 && (
                    <nav className="pagination" aria-label="Paginación">
                        <p>
                            Página {customers.current_page} de {customers.last_page} · {customers.total} clientes
                        </p>
                        <div>
                            {customers.prev_page_url ? (
                                <Link className="button" href={customers.prev_page_url}>
                                    Anterior
                                </Link>
                            ) : (
                                <button className="button" disabled>
                                    Anterior
                                </button>
                            )}
                            {customers.next_page_url ? (
                                <Link className="button" href={customers.next_page_url}>
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
            </section>
        </Layout>
    );
}
