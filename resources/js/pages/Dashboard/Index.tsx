import { Link } from '@inertiajs/react';
import Layout from '../../components/Layout';
import type { CommercialDashboard, DashboardPriority, DashboardSummary } from '../../types';

type Props = { dashboard: CommercialDashboard };

const summaryAmount = (summary: DashboardSummary) =>
    summary.amounts.length === 0 ? (
        <span className="dashboard-no-money">Sin importes pendientes</span>
    ) : (
        <span className="dashboard-amounts">
            {summary.amounts.map((amount) => (
                <span key={amount.currency}>
                    {amount.currency} {amount.display_amount}
                </span>
            ))}
        </span>
    );

function PriorityRow({ priority }: { priority: DashboardPriority }) {
    return (
        <article className={'dashboard-priority priority-' + priority.kind}>
            <div>
                <p className="eyebrow">{priority.reason}</p>
                <h3>{priority.customer_name}</h3>
                <p>
                    {priority.number} · {priority.scheduled_at}
                    {priority.assignee_name ? ' · ' + priority.assignee_name : ''}
                </p>
            </div>
            <div className="dashboard-priority-value">
                <strong>
                    {priority.currency} {priority.display_amount}
                </strong>
                <Link href={'/quotes/' + priority.quote_id} className="text-button">
                    {priority.cta_label}
                </Link>
            </div>
        </article>
    );
}

function Metric({
    title,
    description,
    href,
    summary,
}: {
    title: string;
    description: string;
    href: string;
    summary: DashboardSummary;
}) {
    return (
        <Link className="dashboard-metric" href={href}>
            <span>{title}</span>
            <strong>{summary.count}</strong>
            {summaryAmount(summary)}
            <p>{description}</p>
        </Link>
    );
}

export default function Dashboard({ dashboard }: Props) {
    if (!dashboard.has_activity) {
        return (
            <Layout title="Dashboard">
                <div className="dashboard-empty">
                    <p className="eyebrow">Inicio comercial</p>
                    <h1>Todavía no tienes actividad comercial.</h1>
                    <p>Crea tu primer cliente y cotización para empezar a convertir consultas en oportunidades.</p>
                    <Link href="/quotes/create" className="button primary">
                        Nueva cotización
                    </Link>
                </div>
            </Layout>
        );
    }

    const { metrics } = dashboard;
    return (
        <Layout title="Dashboard">
            <div className="dashboard-heading">
                <div>
                    <p className="eyebrow">Control comercial</p>
                    <h1>Lo que requiere atención.</h1>
                    <p>Prioriza las oportunidades que necesitan una acción hoy.</p>
                </div>
                <Link href="/quotes/create" className="button primary">
                    Nueva cotización
                </Link>
            </div>

            <section className="dashboard-attention" aria-labelledby="attention-title">
                <div>
                    <p className="eyebrow">Dinero que requiere atención</p>
                    <h2 id="attention-title">
                        {dashboard.attention.count} oportunidades con seguimiento vencido o para hoy
                    </h2>
                </div>
                <div>{summaryAmount(dashboard.attention)}</div>
                <Link href="/follow-ups?status=pending" className="button">
                    Ver seguimientos
                </Link>
            </section>

            <section className="dashboard-today" aria-labelledby="today-title">
                <div className="section-heading">
                    <div>
                        <p className="eyebrow">Para hoy</p>
                        <h2 id="today-title">Seguimientos programados hoy</h2>
                    </div>
                    <Link href="/follow-ups?timing=today" className="text-button">
                        Ver todos
                    </Link>
                </div>
                {dashboard.today_follow_ups.length === 0 ? (
                    <div className="dashboard-inline-empty">
                        <p>No tienes seguimientos pendientes para hoy.</p>
                    </div>
                ) : (
                    <div className="dashboard-priority-list">
                        {dashboard.today_follow_ups.map((priority) => (
                            <PriorityRow key={'today-' + priority.follow_up_id} priority={priority} />
                        ))}
                    </div>
                )}
            </section>

            <section className="dashboard-metrics" aria-label="Métricas comerciales accionables">
                <Metric
                    title="Seguimientos vencidos"
                    description="Requieren contacto inmediato."
                    href="/follow-ups?timing=overdue"
                    summary={metrics.overdue_follow_ups}
                />
                <Metric
                    title="Seguimientos de hoy"
                    description="Acciones programadas para hoy."
                    href="/follow-ups?timing=today"
                    summary={metrics.today_follow_ups}
                />
                <Metric
                    title="Sin seguimiento"
                    description="Cotizaciones enviadas sin próximo contacto."
                    href="/quotes?status=sent&follow_up=missing"
                    summary={metrics.without_follow_up}
                />
                <Metric
                    title="Cotizaciones abiertas"
                    description="Propuestas emitidas que siguen vigentes."
                    href="/quotes?status=sent"
                    summary={metrics.sent}
                />
                <Metric
                    title="Vencidas"
                    description="Propuestas que ya no están vigentes."
                    href="/quotes?status=expired"
                    summary={metrics.expired}
                />
                <Metric
                    title="Aceptadas"
                    description="Resultado comercial confirmado."
                    href="/quotes?status=accepted"
                    summary={metrics.accepted}
                />
            </section>

            <div className="dashboard-columns">
                <section aria-labelledby="priorities-title">
                    <div className="section-heading">
                        <div>
                            <p className="eyebrow">Prioridades</p>
                            <h2 id="priorities-title">Oportunidades para mover ahora</h2>
                        </div>
                        <Link href="/follow-ups" className="text-button">
                            Abrir seguimientos
                        </Link>
                    </div>
                    <div className="dashboard-priority-list">
                        {dashboard.priorities.map((priority) => (
                            <PriorityRow key={priority.kind + '-' + priority.quote_id} priority={priority} />
                        ))}
                    </div>
                </section>
                <section className="dashboard-pipeline" aria-labelledby="pipeline-title">
                    <p className="eyebrow">Pipeline actual</p>
                    <h2 id="pipeline-title">Estado de cotizaciones</h2>
                    <dl>
                        {dashboard.pipeline.map((entry) => (
                            <div key={entry.status}>
                                <dt>{entry.label}</dt>
                                <dd>
                                    <strong>{entry.summary.count}</strong>
                                    {summaryAmount(entry.summary)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    <div className="dashboard-conversion">
                        <span>Tasa de cierre resuelto</span>
                        <strong>
                            {dashboard.conversion.rate === null
                                ? 'Sin cierre todavía'
                                : dashboard.conversion.rate + '%'}
                        </strong>
                        <p>
                            {dashboard.conversion.accepted} aceptadas de {dashboard.conversion.resolved} cotizaciones
                            resueltas.
                        </p>
                    </div>
                </section>
            </div>
        </Layout>
    );
}
