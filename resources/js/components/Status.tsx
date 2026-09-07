import type { CustomerStatus } from '../types';
export default function Status({ status }: { status: CustomerStatus }) {
    return <span className={'status ' + status}>{status === 'active' ? 'Activo' : 'Inactivo'}</span>;
}
