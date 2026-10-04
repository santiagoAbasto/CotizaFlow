import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../components/Layout';
import Status from '../../components/Status';
import type { FollowUp, FollowUpAssignee, Quote } from '../../types';

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

export default function Show({
    quote,
    followUps,
    followUpAssignees,
}: {
    quote: Quote;
    followUps: FollowUp[];
    followUpAssignees: FollowUpAssignee[];
}) {
    const [confirmation, setConfirmation] = useState<Transition | null>(null);
    const [rejectionReason, setRejectionReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [transitionError, setTransitionError] = useState<string | null>(null);
    const [scheduling, setScheduling] = useState(false);
    const [followUpError, setFollowUpError] = useState<string | null>(null);
    const [followUpOpen, setFollowUpOpen] = useState(false);
    const [scheduledAt, setScheduledAt] = useState('');
    const [assignedTo, setAssignedTo] = useState('');
    const [followUpNote, setFollowUpNote] = useState('');

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
    const pendingFollowUp = followUps.find((followUp) => followUp.status === 'pending');

    function scheduleFollowUp() {
        setFollowUpError(null);
        router.post(
            `/quotes/${quote.id}/follow-ups`,
            { scheduled_at: scheduledAt, assigned_to: assignedTo || null, note: followUpNote },
            {
                preserveScroll: true,
                onStart: () => setScheduling(true),
                onError: (errors) =>
                    setFollowUpError(
                        errors.scheduled_at ??
                            errors.assigned_to ??
                            errors.status ??
                            'No se pudo programar el seguimiento.',
                    ),
                onSuccess: () => setFollowUpOpen(false),
                onFinish: () => setScheduling(false),
            },
        );
    }

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
            <section className="follow-up-panel" aria-labelledby="follow-up-heading">
                <div>
                    <p className="eyebrow">Seguimiento</p>
                    <h2 id="follow-up-heading">Próximo contacto</h2>
                </div>
                {pendingFollowUp ? (
                    <div className="follow-up-next">
                        <div>
                            <strong>
                                {pendingFollowUp.timing_label ?? pendingFollowUp.status_label} ·{' '}
                                {pendingFollowUp.scheduled_at}
                            </strong>
                            <p>
                                {pendingFollowUp.assignee_name}
                                {pendingFollowUp.note ? ` · ${pendingFollowUp.note}` : ''}
                            </p>
                        </div>
                        <Link href="/follow-ups" className="text-link">
                            Ver seguimientos
                        </Link>
                    </div>
                ) : quote.status === 'sent' ? (
                    <div className="follow-up-next">
                        <p>Programa el próximo contacto para que esta oportunidad no se pierda.</p>
                        <button className="button primary" onClick={() => setFollowUpOpen(true)}>
                            Programar seguimiento
                        </button>
                    </div>
                ) : (
                    <p className="follow-up-muted">
                        Los seguimientos realizados aparecerán aquí. Las nuevas acciones comerciales se programan sobre
                        cotizaciones enviadas.
                    </p>
                )}
                {followUps.length > 0 && (
                    <ol className="follow-up-history" aria-label="Historial de seguimientos">
                        {followUps.map((followUp) => (
                            <li key={followUp.id}>
                                <strong>{followUp.status_label}</strong>
                                <span>
                                    {followUp.scheduled_at} · {followUp.assignee_name}
                                </span>
                                {followUp.outcome_label && (
                                    <p>
                                        {followUp.outcome_label}
                                        {followUp.outcome_note ? ` · ${followUp.outcome_note}` : ''}
                                    </p>
                                )}
                                {!followUp.outcome_label && followUp.note && <p>{followUp.note}</p>}
                            </li>
                        ))}
                    </ol>
                )}
            </section>
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
            {followUpOpen && (
                <dialog open className="confirmation-dialog" aria-labelledby="follow-up-dialog-title">
                    <div>
                        <p className="eyebrow">Seguimiento</p>
                        <h2 id="follow-up-dialog-title">Programar contacto</h2>
                        <p>La fecha y hora quedan visibles para el responsable en Seguimientos.</p>
                        <label htmlFor="follow_up_scheduled_at">
                            Fecha y hora
                            <input
                                id="follow_up_scheduled_at"
                                className="input"
                                type="datetime-local"
                                value={scheduledAt}
                                onChange={(event) => setScheduledAt(event.target.value)}
                                required
                            />
                        </label>
                        <label htmlFor="follow_up_assigned_to">
                            Responsable
                            <select
                                id="follow_up_assigned_to"
                                className="input"
                                value={assignedTo}
                                onChange={(event) => setAssignedTo(event.target.value)}
                            >
                                <option value="">Yo (responsable actual)</option>
                                {followUpAssignees.map((assignee) => (
                                    <option value={assignee.id} key={assignee.id}>
                                        {assignee.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label htmlFor="follow_up_note">
                            Nota <span className="required">(opcional)</span>
                            <textarea
                                id="follow_up_note"
                                className="input"
                                value={followUpNote}
                                maxLength={2000}
                                onChange={(event) => setFollowUpNote(event.target.value)}
                            />
                        </label>
                        {followUpError && (
                            <p role="alert" className="field-error">
                                {followUpError}
                            </p>
                        )}
                        <div className="dialog-actions">
                            <button className="button" disabled={scheduling} onClick={() => setFollowUpOpen(false)}>
                                Cancelar
                            </button>
                            <button className="button primary" disabled={scheduling} onClick={scheduleFollowUp}>
                                {scheduling ? 'Programando…' : 'Programar seguimiento'}
                            </button>
                        </div>
                    </div>
                </dialog>
            )}
        </Layout>
    );
}
