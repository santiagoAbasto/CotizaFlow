import { Head, Link, useForm } from '@inertiajs/react';
export default function Companies({ companies }: { companies: { id: number; name: string }[] }) {
    const form = useForm({ company_id: 0 });
    function select(id: number) {
        form.transform(() => ({ company_id: id }));
        form.post('/companies/select');
    }
    return (
        <main className="auth-page">
            <Head title="Seleccionar empresa" />
            <div className="auth-panel">
                <div className="brand">
                    Cotiza<span>Flow</span>
                </div>
                <h1>Elige tu empresa.</h1>
                <p className="muted">Los clientes y su información pertenecen a la empresa seleccionada.</p>
                <div className="company-list">
                    {companies.map((c) => (
                        <button
                            className="company-choice"
                            key={c.id}
                            disabled={form.processing}
                            onClick={() => select(c.id)}
                        >
                            {c.name}
                            <span>Abrir clientes</span>
                        </button>
                    ))}
                </div>
                {companies.length === 0 && (
                    <p role="status">No tienes acceso activo a ninguna empresa. Solicita acceso a su administrador.</p>
                )}
                {form.errors.company_id && <p role="alert">{form.errors.company_id}</p>}
                <Link href="/logout" method="post" as="button" className="text-button">
                    Cerrar sesión
                </Link>
            </div>
        </main>
    );
}
