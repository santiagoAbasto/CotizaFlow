import type { CatalogStatus, CustomerStatus } from '../types';
export default function Status({ status }: { status: CustomerStatus | CatalogStatus }) {
    return <span className={'status ' + status}>{status === 'active' ? 'Activo' : 'Inactivo'}</span>;
}
