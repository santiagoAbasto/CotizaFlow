import type { CatalogStatus, CustomerStatus, QuoteStatus } from '../types';
export default function Status({ status }: { status: CustomerStatus | CatalogStatus | QuoteStatus }) {
    const labels: Record<CustomerStatus | QuoteStatus, string> = { active: 'Activo', inactive: 'Inactivo', draft: 'Borrador', sent: 'Enviada', accepted: 'Aceptada', rejected: 'Rechazada', expired: 'Vencida' };
    return <span className={'status ' + status}>{labels[status]}</span>;
}
