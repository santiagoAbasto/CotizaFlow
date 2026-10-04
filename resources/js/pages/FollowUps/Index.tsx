import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '../../components/Layout';
import Status from '../../components/Status';
import type { FollowUp, FollowUpAssignee, FollowUpOutcome } from '../../types';

type Filters = { q: string; status: string; timing: string };
type PaginatedFollowUps = {
    data: FollowUp[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Dialog = { kind: 'complete' | 'reschedule' | 'cancel'; followUp: FollowUp } | null;

export default function Index({
    followUps,
    filters,
    assignees,
    outcomes,
    dueValue,
}: {
    followUps: PaginatedFollowUps;
    filters: Filters;
    assignees: FollowUpAssignee[];
    outcomes: { value: FollowUpOutcome; label: string }[];
    dueValue: { currency: string; total: string }[];
}) {
    const [query, setQuery] = useState(filters.q);
    const [dialog, setDialog] = useState<Dialog>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [scheduledAt, setScheduledAt] = useState('');
    const [assignedTo, setAssignedTo] = useState('');
    const [note, setNote] = useState('');
    const [outcome, setOutcome] = useState<FollowUpOutcome>('contacted');
    const [outcomeNote, setOutcomeNote] = useState('');
    const [scheduleNext, setScheduleNext] = useState(false);
    const [nextScheduledAt, setNextScheduledAt] = useState('');
    const [nextAssignee, setNextAssignee] = useState('');
    const [nextNote, setNextNote] = useState('');
    const [cancellationReason, setCancellationReason] = useState('');

    function applyFilters(next: Partial<Filters>) {
        router.get(
            '/follow-ups',
            { q: query, status: filters.status, timing: filters.timing, ...next },
            { preserveState: true, replace: true },
        );
    }

    function openDialog(kind: NonNullable<Dialog>['kind'], followUp: FollowUp) {
        setError(null);
        setScheduledAt(followUp.scheduled_at_input);
        setAssignedTo(String(followUp.assigned_to));
        setNote(followUp.note ?? '');
        setOutcome('contacted');
        setOutcomeNote('');
        setScheduleNext(false);
        setNextScheduledAt('');
        setNextAssignee(String(followUp.assigned_to));
        setNextNote('');
        setCancellationReason('');
        setDialog({ kind, followUp });
    }

    function submitDialog() {
        if (!dialog) return;
        setError(null);
        const url =
            dialog.kind === 'complete'
                ? `/follow-ups/${dialog.followUp.id}/complete`
                : dialog.kind === 'cancel'
                  ? `/follow-ups/${dialog.followUp.id}/cancel`
                  : `/follow-ups/${dialog.followUp.id}`;
        const data =
            dialog.kind === 'complete'
                ? {
                      outcome,
                      outcome_note: outcomeNote,
                      schedule_next: scheduleNext,
                      next_scheduled_at: nextScheduledAt,
                      next_assigned_to: nextAssignee || null,
                      next_note: nextNote,
                  }
                : dialog.kind === 'cancel'
                  ? { cancellation_reason: cancellationReason }
                  : { scheduled_at: scheduledAt, assigned_to: assignedTo || null, note };
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onError: (errors: Record<string, string>) =>
                setError(
                    errors.status ??
                        errors.scheduled_at ??
                        errors.next_scheduled_at ??
                        errors.assigned_to ??
                        errors.next_assigned_to ??
                        errors.outcome ??
                        'No se pudo actualizar el seguimiento.',
                ),
            onSuccess: () => setDialog(null),
            onFinish: () => setProcessing(false),
        };

        if (dialog.kind === 'reschedule') {
            router.put(url, data, options);

            return;
        }

        router.post(url, data, options);
    }

    return (
        <Layout title="Seguimientos">
            <div className="page-heading">
                <div>
                    <p className="eyebrow">Centro operativo</p>
                    <h1>Seguimientos</h1>
                    <p>Atiende primero las cotizaciones que requieren una respuesta hoy.</p>
                </div>
                {dueValue.length > 0 && (
                    <p className="follow-up-value-at-risk">
                        Pendiente de contacto hoy o vencido:{' '}
                        {dueValue.map(({ currency, total }) => `${currency} ${total}`).join(' · ')}
                    </p>
                )}
            </div>
            <form
                className="filter-bar"
                onSubmit={(event) => {
                    event.preventDefault();
                    applyFilters({ q: query });
                }}
            >
                <label className="search-field">
                    <span>Buscar</span>
                    <input
                        className="input"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Cotización o cliente"
                    />
                </label>
                <label>
                    Estado
                    <select
                        className="input"
                        value={filters.status}
                        onChange={(event) => applyFilters({ status: event.target.value })}
                    >
                        <option value="pending">Pendientes</option>
                        <option value="completed">Completados</option>
                        <option value="cancelled">Cancelados</option>
                        <option value="all">Todos</option>
                    </select>
                </label>
                <label>
                    Momento
                    <select
                        className="input"
                        value={filters.timing}
                        onChange={(event) => applyFilters({ timing: event.target.value })}
                    >
                        <option value="all">Todos</option>
                        <option value="overdue">Vencidos</option>
                        <option value="today">Hoy</option>
                        <option value="upcoming">Próximos</option>
                    </select>
                </label>
                <button className="button" type="submit">
                    Buscar
                </button>
            </form>
            {followUps.data.length === 0 ? (
                <section className="empty-state">
                    <p className="eyebrow">Sin acciones pendientes</p>
                    <h2>No hay seguimientos para este filtro.</h2>
                    <p>Programa un contacto desde una cotización enviada para que una oportunidad no quede olvidada.</p>
                    <Link href="/quotes" className="button primary">
                        Ver cotizaciones
                    </Link>
                </section>
            ) : (
                <section className="follow-up-list" aria-label="Lista de seguimientos">
                    {followUps.data.map((followUp) => (
                        <article className={`follow-up-row ${followUp.timing}`} key={followUp.id}>
                            <div className="follow-up-main">
                                <div className="follow-up-row-heading">
                                    <Link href={`/quotes/${followUp.quote_id}`}>{followUp.customer_name}</Link>
                                    <Status status={followUp.status} />
                                </div>
                                <p>
                                    <strong>{followUp.quote_number}</strong> · {followUp.quote_currency}{' '}
                                    {followUp.quote_total}
                                </p>
                                {followUp.note && <p className="follow-up-note">{followUp.note}</p>}
                                {followUp.outcome_label && (
                                    <p className="follow-up-note">
                                        {followUp.outcome_label}
                                        {followUp.outcome_note ? ` · ${followUp.outcome_note}` : ''}
                                    </p>
                                )}
                            </div>
                            <div className="follow-up-when">
                                {followUp.timing_label && (
                                    <strong className={`timing ${followUp.timing}`}>{followUp.timing_label}</strong>
                                )}
                                <time>{followUp.scheduled_at}</time>
                                <span>{followUp.assignee_name}</span>
                            </div>
                            <div className="row-actions">
                                <Link href={`/quotes/${followUp.quote_id}`} className="text-link">
                                    Abrir cotización
                                </Link>
                                {followUp.status === 'pending' && (
                                    <>
                                        <button
                                            className="text-button"
                                            onClick={() => openDialog('complete', followUp)}
                                        >
                                            Completar
                                        </button>
                                        <button
                                            className="text-button"
                                            onClick={() => openDialog('reschedule', followUp)}
                                        >
                                            Reprogramar
                                        </button>
                                        <button
                                            className="text-button danger"
                                            onClick={() => openDialog('cancel', followUp)}
                                        >
                                            Cancelar
                                        </button>
                                    </>
                                )}
                            </div>
                        </article>
                    ))}
                </section>
            )}
            {followUps.total > 0 && (
                <nav className="pagination" aria-label="Paginación de seguimientos">
                    <span>
                        {followUps.from}–{followUps.to} de {followUps.total}
                    </span>
                    <div>
                        {followUps.prev_page_url && (
                            <Link href={followUps.prev_page_url} className="button">
                                Anterior
                            </Link>
                        )}
                        {followUps.next_page_url && (
                            <Link href={followUps.next_page_url} className="button">
                                Siguiente
                            </Link>
                        )}
                    </div>
                </nav>
            )}
            {dialog && (
                <dialog open className="confirmation-dialog" aria-labelledby="follow-up-action-title">
                    <div>
                        <p className="eyebrow">
                            {dialog.followUp.quote_number} · {dialog.followUp.customer_name}
                        </p>
                        <h2 id="follow-up-action-title">
                            {dialog.kind === 'complete'
                                ? 'Completar seguimiento'
                                : dialog.kind === 'reschedule'
                                  ? 'Reprogramar seguimiento'
                                  : 'Cancelar seguimiento'}
                        </h2>
                        {dialog.kind === 'reschedule' && (
                            <ScheduleFields
                                assignees={assignees}
                                scheduledAt={scheduledAt}
                                setScheduledAt={setScheduledAt}
                                assignedTo={assignedTo}
                                setAssignedTo={setAssignedTo}
                                note={note}
                                setNote={setNote}
                            />
                        )}
                        {dialog.kind === 'complete' && (
                            <>
                                <label htmlFor="follow_up_outcome">
                                    Resultado
                                    <select
                                        id="follow_up_outcome"
                                        className="input"
                                        value={outcome}
                                        onChange={(event) => setOutcome(event.target.value as FollowUpOutcome)}
                                    >
                                        {outcomes.map((item) => (
                                            <option key={item.value} value={item.value}>
                                                {item.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label htmlFor="follow_up_outcome_note">
                                    Nota <span className="required">(opcional)</span>
                                    <textarea
                                        id="follow_up_outcome_note"
                                        className="input"
                                        value={outcomeNote}
                                        maxLength={2000}
                                        onChange={(event) => setOutcomeNote(event.target.value)}
                                    />
                                </label>
                                <label className="checkbox-field">
                                    <input
                                        type="checkbox"
                                        checked={scheduleNext}
                                        onChange={(event) => setScheduleNext(event.target.checked)}
                                    />{' '}
                                    Programar próximo seguimiento
                                </label>
                                {scheduleNext && (
                                    <ScheduleFields
                                        assignees={assignees}
                                        scheduledAt={nextScheduledAt}
                                        setScheduledAt={setNextScheduledAt}
                                        assignedTo={nextAssignee}
                                        setAssignedTo={setNextAssignee}
                                        note={nextNote}
                                        setNote={setNextNote}
                                        prefix="next_"
                                    />
                                )}
                            </>
                        )}
                        {dialog.kind === 'cancel' && (
                            <label htmlFor="follow_up_cancel_reason">
                                Motivo <span className="required">(opcional)</span>
                                <textarea
                                    id="follow_up_cancel_reason"
                                    className="input"
                                    value={cancellationReason}
                                    maxLength={1000}
                                    onChange={(event) => setCancellationReason(event.target.value)}
                                />
                            </label>
                        )}
                        {error && (
                            <p role="alert" className="field-error">
                                {error}
                            </p>
                        )}
                        <div className="dialog-actions">
                            <button className="button" disabled={processing} onClick={() => setDialog(null)}>
                                Volver
                            </button>
                            <button className="button primary" disabled={processing} onClick={submitDialog}>
                                {processing
                                    ? 'Guardando…'
                                    : dialog.kind === 'complete'
                                      ? 'Completar'
                                      : dialog.kind === 'reschedule'
                                        ? 'Guardar nueva fecha'
                                        : 'Cancelar seguimiento'}
                            </button>
                        </div>
                    </div>
                </dialog>
            )}
        </Layout>
    );
}

function ScheduleFields({
    assignees,
    scheduledAt,
    setScheduledAt,
    assignedTo,
    setAssignedTo,
    note,
    setNote,
    prefix = '',
}: {
    assignees: FollowUpAssignee[];
    scheduledAt: string;
    setScheduledAt: (value: string) => void;
    assignedTo: string;
    setAssignedTo: (value: string) => void;
    note: string;
    setNote: (value: string) => void;
    prefix?: string;
}) {
    return (
        <>
            <label htmlFor={`${prefix}scheduled_at`}>
                Fecha y hora
                <input
                    id={`${prefix}scheduled_at`}
                    className="input"
                    type="datetime-local"
                    value={scheduledAt}
                    onChange={(event) => setScheduledAt(event.target.value)}
                    required
                />
            </label>
            <label htmlFor={`${prefix}assigned_to`}>
                Responsable
                <select
                    id={`${prefix}assigned_to`}
                    className="input"
                    value={assignedTo}
                    onChange={(event) => setAssignedTo(event.target.value)}
                >
                    {assignees.map((assignee) => (
                        <option value={assignee.id} key={assignee.id}>
                            {assignee.name}
                        </option>
                    ))}
                </select>
            </label>
            <label htmlFor={`${prefix}note`}>
                Nota <span className="required">(opcional)</span>
                <textarea
                    id={`${prefix}note`}
                    className="input"
                    value={note}
                    maxLength={2000}
                    onChange={(event) => setNote(event.target.value)}
                />
            </label>
        </>
    );
}
