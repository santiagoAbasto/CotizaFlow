import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Layout from '../../components/Layout';
import Status from '../../components/Status';
import type { Currency, QuoteStatus } from '../../types';

type QuoteRow = {
    id: number;
    number: string;
    customer_name_snapshot: string;
    currency: Currency;
    issued_at: string;
    expires_at: string | null;
    status: QuoteStatus;
    status_label: string;
    display_total: string;
    updated_at: string;
};
type Props = {
    quotes: {
        data: QuoteRow[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { q: string; status: string; follow_up: string };
};

export default function Index({ quotes, filters }: Props) {
    const [q, setQ] = useState(filters.q);
    const [status, setStatus] = useState(filters.status);
    const [followUp, setFollowUp] = useState(filters.follow_up);
    const [loading, setLoading] = useState(false);
    function search(e: FormEvent) {
        e.preventDefault();
        router.get(
            '/quotes',
            { q, status, follow_up: followUp },
            { preserveState: true, onStart: () => setLoading(true), onFinish: () => setLoading(false) },
        );
    }
    return (
        <Layout title="Cotizaciones">
            <div className="page-heading">
                <div>
                    <p className="eyebrow">Propuestas comerciales</p>
                    <h1>
                        Cotizaciones <span className="count">{quotes.total}</span>
                    </h1>
                    <p className="muted">Crea una propuesta clara desde cliente, productos y precios confirmados.</p>
                </div>
                <div>
                    <label htmlFor="follow_up">Seguimiento</label>
                    <select
                        id="follow_up"
                        className="input"
                        value={followUp}
                        onChange={(e) => setFollowUp(e.target.value)}
                    >
                        <option value="">Todos</option>
                        <option value="missing">Sin seguimiento programado</option>
                    </select>
                </div>
                <Link href="/quotes/create" className="button primary">
                    Nueva cotización
                </Link>
            </div>
            <form onSubmit={search} className="filters" aria-label="Buscar cotizaciones">
                <div className="search-field">
                    <label htmlFor="q">Buscar cotización</label>
                    <input
                        id="q"
                        className="input"
                        type="search"
                        placeholder="Número o cliente"
                        value={q}
                        maxLength={100}
                        onChange={(e) => setQ(e.target.value)}
                    />
                </div>
                <div>
                    <label htmlFor="status">Estado</label>
                    <select id="status" className="input" value={status} onChange={(e) => setStatus(e.target.value)}>
                        <option value="">Todos</option>
                        <option value="draft">Borrador</option>
                        <option value="sent">Enviada</option>
                        <option value="accepted">Aceptada</option>
                        <option value="rejected">Rechazada</option>
                        <option value="expired">Vencida</option>
                    </select>
                </div>
                <button className="button" disabled={loading}>
                    {loading ? 'Buscando…' : 'Buscar'}
                </button>
                {(filters.q || filters.status || filters.follow_up) && (
                    <Link href="/quotes" className="text-button">
                        Limpiar filtros
                    </Link>
                )}
            </form>
            <section aria-label="Listado de cotizaciones" aria-busy={loading}>
                {quotes.data.length === 0 ? (
                    <div className="empty-state">
                        <h2>
                            {filters.q || filters.status || filters.follow_up
                                ? 'No encontramos cotizaciones con esos filtros.'
                                : 'Todavía no tienes cotizaciones.'}
                        </h2>
                        <p>
                            {filters.q || filters.status || filters.follow_up
                                ? 'Prueba otro número, cliente o estado.'
                                : 'Crea tu primera cotización y conviértela en una propuesta profesional para tu cliente.'}
                        </p>
                        {!filters.q && !filters.status && !filters.follow_up && (
                            <Link href="/quotes/create" className="button primary">
                                Nueva cotización
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="quote-table">
                        <div className="quote-head">
                            <span>Número</span>
                            <span>Cliente</span>
                            <span>Fecha</span>
                            <span>Vigencia</span>
                            <span>Estado</span>
                            <span>Total</span>
                        </div>
                        {quotes.data.map((quote) => (
                            <article className="quote-row" key={quote.id}>
                                <Link className="quote-number" href={'/quotes/' + quote.id}>
                                    {quote.number}
                                </Link>
                                <strong>{quote.customer_name_snapshot}</strong>
                                <time>{quote.issued_at}</time>
                                <time>{quote.expires_at ?? 'Sin vencimiento'}</time>
                                <Status status={quote.status} />
                                <span className="money strong">
                                    {quote.currency} {quote.display_total}
                                </span>
                            </article>
                        ))}
                    </div>
                )}
                {quotes.total > 0 && (
                    <nav className="pagination" aria-label="Paginación">
                        <p>
                            Página {quotes.current_page} de {quotes.last_page} · {quotes.total} cotizaciones
                        </p>
                        <div>
                            {quotes.prev_page_url ? (
                                <Link className="button" href={quotes.prev_page_url}>
                                    Anterior
                                </Link>
                            ) : (
                                <button className="button" disabled>
                                    Anterior
                                </button>
                            )}
                            {quotes.next_page_url ? (
                                <Link className="button" href={quotes.next_page_url}>
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
