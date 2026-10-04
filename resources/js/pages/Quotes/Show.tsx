import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../components/Layout';
import Status from '../../components/Status';
import type { Quote } from '../../types';

type Transition = 'send' | 'accept' | 'reject';

const transitionCopy: Record<Transition, { title: string; body: string; action: string }> = {
    send: {
        title: 'Marcar como enviada',
        body: 'Úsalo cuando ya hayas compartido esta cotización con el cliente por un canal externo.',
        action: 'Marcar como enviada',
    },
    accept: {
        title: 'Marcar como aceptada',
        body: 'Confirma que el cliente aceptó esta propuesta. Esta acción bloquea futuras ediciones.',
        action: 'Marcar como aceptada',
    },
    reject: {
        title: 'Marcar como rechazada',
        body: 'Registra que el cliente no continuará con esta propuesta. Esta acción bloquea futuras ediciones.',
        action: 'Marcar como rechazada',
    },
};

export default function Show({ quote }: { quote: Quote }) {
    const [confirmation, setConfirmation] = useState<Transition | null>(null);
    const [rejectionReason, setRejectionReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [transitionError, setTransitionError] = useState<string | null>(null);

    function submitTransition() {
        if (!confirmation) return;
        setTransitionError(null);
        router.post(
            `/quotes/${quote.id}/${confirmation}`,
            confirmation === 'reject' ? { rejection_reason: rejectionReason } : {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onError: (errors) =>
                    setTransitionError(
                        errors.status ?? errors.rejection_reason ?? 'No se pudo actualizar la cotización.',
                    ),
                onSuccess: () => setConfirmation(null),
                onFinish: () => setProcessing(false),
            },
        );
    }

    const pdfUrl = `/quotes/${quote.id}/pdf`;

    return (
        <Layout title={quote.number}>
            <Link href="/quotes" className="back-link">
                Volver a cotizaciones
            </Link>
            <div className="page-heading quote-title">
                <div>
                    <p className="eyebrow">Cotización</p>
                    <h1>{quote.number}</h1>
                    <Status status={quote.status} />
                </div>
                <div className="quote-actions" aria-label="Acciones de la cotización">
                    <a href={pdfUrl} target="_blank" rel="noreferrer" className="button">
                        Ver PDF{quote.status === 'draft' ? ' borrador' : ''}
                    </a>
                    <a href={pdfUrl + '?download=1'} className="button">
                        Descargar
                    </a>
                    {quote.status === 'draft' && (
                        <>
                            <Link href={`/quotes/${quote.id}/edit`} className="button primary">
                                Editar borrador
                            </Link>
                            <button className="button primary" onClick={() => setConfirmation('send')}>
                                Marcar como enviada
                            </button>
                        </>
                    )}
                    {quote.status === 'sent' && (
                        <>
                            <button className="button primary" onClick={() => setConfirmation('accept')}>
                                Marcar aceptada
                            </button>
                            <button className="button" onClick={() => setConfirmation('reject')}>
                                Marcar rechazada
                            </button>
                        </>
                    )}
                </div>
            </div>
            <div className="quote-meta">
                <section>
                    <span>Cliente</span>
                    <strong>{quote.customer_name_snapshot}</strong>
                    {quote.customer_legal_name_snapshot && <p>{quote.customer_legal_name_snapshot}</p>}
                    {quote.customer_tax_id_snapshot && <p>ID fiscal: {quote.customer_tax_id_snapshot}</p>}
                </section>
                <section>
                    <span>Fecha y vigencia</span>
                    <strong>{quote.issued_at}</strong>
                    <p>{quote.expires_at ? 'Válida hasta ' + quote.expires_at : 'Sin vencimiento'}</p>
                </section>
                <section>
                    <span>Estado comercial</span>
                    <strong>{quote.status_label}</strong>
                    {quote.sent_at && <p>Enviada el {quote.sent_at}</p>}
                    {quote.accepted_at && <p>Aceptada el {quote.accepted_at}</p>}
                    {quote.rejected_at && <p>Rechazada el {quote.rejected_at}</p>}
                    {quote.expired_at && <p>Vencida el {quote.expired_at}</p>}
                </section>
            </div>
            {quote.status === 'rejected' && quote.rejection_reason && (
                <section className="quote-status-note" aria-label="Motivo de rechazo">
                    <strong>Motivo de rechazo</strong>
                    <p className="preserve-text">{quote.rejection_reason}</p>
                </section>
            )}
            <section className="quote-document" aria-label="Detalle de la cotización">
                <div className="quote-items-head">
                    <span>Producto</span>
                    <span>Cantidad</span>
                    <span>Precio</span>
                    <span>Desc.</span>
                    <span>Impuesto</span>
                    <span>Total</span>
                </div>
                {quote.items.map((item) => (
                    <article className="quote-item-display" key={item.id}>
                        <div>
                            <span className="sku">{item.sku_snapshot}</span>
                            <strong>{item.name_snapshot}</strong>
                            <small>
                                {item.unit_label} ·{' '}
                                {item.price_source === 'price_list'
                                    ? item.source_price_list_name_snapshot
                                    : 'Precio base'}
                            </small>
                        </div>
                        <span className="money">
                            <small className="mobile-display-label">Cantidad</small>
                            {item.display_quantity}
                        </span>
                        <span className="money">
                            <small className="mobile-display-label">Precio</small>
                            {quote.currency} {item.display_unit_price}
                        </span>
                        <span>
                            <small className="mobile-display-label">Descuento</small>
                            {item.display_discount_percent}%
                        </span>
                        <span>
                            <small className="mobile-display-label">Impuesto</small>
                            {item.display_tax_percent}%
                        </span>
                        <strong className="money">
                            <small className="mobile-display-label">Total línea</small>
                            {quote.currency} {item.display_line_total}
                        </strong>
                    </article>
                ))}
                <div className="quote-summary">
                    <dl>
                        <div>
                            <dt>Subtotal</dt>
                            <dd>
                                {quote.currency} {quote.display_subtotal}
                            </dd>
                        </div>
                        <div>
                            <dt>Descuento</dt>
                            <dd>
                                − {quote.currency} {quote.display_discount_total}
                            </dd>
                        </div>
                        <div>
                            <dt>Impuestos</dt>
                            <dd>
                                {quote.currency} {quote.display_tax_total}
                            </dd>
                        </div>
                        <div className="grand-total">
                            <dt>Total</dt>
                            <dd>
                                {quote.currency} {quote.display_total}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>
            {(quote.notes || quote.terms) && (
                <div className="quote-copy">
                    {quote.notes && (
                        <section>
                            <h2>Observaciones</h2>
                            <p className="preserve-text">{quote.notes}</p>
                        </section>
                    )}
                    {quote.terms && (
                        <section>
                            <h2>Condiciones</h2>
                            <p className="preserve-text">{quote.terms}</p>
                        </section>
                    )}
                </div>
            )}
            {confirmation && (
                <dialog open className="confirmation-dialog" aria-labelledby="transition-title">
                    <div>
                        <p className="eyebrow">Confirmar acción</p>
                        <h2 id="transition-title">{transitionCopy[confirmation].title}</h2>
                        <p>{transitionCopy[confirmation].body}</p>
                        {confirmation === 'reject' && (
                            <label htmlFor="rejection_reason">
                                Motivo de rechazo <span className="required">(opcional)</span>
                                <textarea
                                    id="rejection_reason"
                                    className="input"
                                    value={rejectionReason}
                                    maxLength={1000}
                                    onChange={(event) => setRejectionReason(event.target.value)}
                                />
                            </label>
                        )}
                        {transitionError && (
                            <p role="alert" className="field-error">
                                {transitionError}
                            </p>
                        )}
                        <div className="dialog-actions">
                            <button className="button" disabled={processing} onClick={() => setConfirmation(null)}>
                                Cancelar
                            </button>
                            <button className="button primary" disabled={processing} onClick={submitTransition}>
                                {processing ? 'Guardando…' : transitionCopy[confirmation].action}
                            </button>
                        </div>
                    </div>
                </dialog>
            )}
        </Layout>
    );
}
