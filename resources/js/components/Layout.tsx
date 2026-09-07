import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

export default function Layout({ title, children }: { title: string; children: ReactNode }) {
    const { url } = usePage();
    const { auth, company, flash } = usePage<{
        auth: { user: { name: string } | null };
        company: { name: string } | null;
        flash: { success: string | null };
    }>().props;
    return <div className="app-shell">
        <Head title={title} />
        <a href="#main" className="skip-link">Ir al contenido</a>
        <header className="app-header">
            <Link href="/customers" className="brand">Cotiza<span>Flow</span></Link>
            <div className="account">
                {company && <Link href="/companies/select" className="company-link">{company.name}<span>Cambiar empresa</span></Link>}
                <span className="user-name">{auth.user?.name}</span>
                <Link href="/logout" method="post" as="button" className="text-button">Salir</Link>
            </div>
        </header>
        <aside className="sidebar"><span className="nav-caption">Comercial</span><Link href="/customers" className="nav-active" aria-current="page">Clientes</Link><p>Tu directorio comercial,<br />siempre a mano.</p></aside>
        <main id="main" className="workspace">
            {flash.success && <div key={flash.success + url} role="status" className="notice">{flash.success}</div>}
            {children}
        </main>
    </div>;
}
