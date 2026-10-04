import type { CatalogStatus, CustomerStatus, FollowUpStatus, QuoteStatus } from '../types';
export default function Status({ status }: { status: CustomerStatus | CatalogStatus | QuoteStatus | FollowUpStatus }) {
    const labels: Record<CustomerStatus | QuoteStatus | FollowUpStatus, string> = {
        active: 'Activo',
        inactive: 'Inactivo',
        draft: 'Borrador',
        sent: 'Enviada',
        accepted: 'Aceptada',
        rejected: 'Rechazada',
        expired: 'Vencida',
        pending: 'Pendiente',
        completed: 'Completado',
        cancelled: 'Cancelado',
    };
    return <span className={'status ' + status}>{labels[status]}</span>;
}
