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
        <aside className="sidebar"><span className="nav-caption">Comercial</span><nav aria-label="Navegación principal" className="main-nav">
            <Link href="/customers" className={url.startsWith('/customers') ? 'nav-active' : ''} aria-current={url.startsWith('/customers') ? 'page' : undefined}>Clientes</Link>
            <Link href="/products" className={url.startsWith('/products') ? 'nav-active' : ''} aria-current={url.startsWith('/products') ? 'page' : undefined}>Productos</Link>
            <Link href="/price-lists" className={url.startsWith('/price-lists') ? 'nav-active' : ''} aria-current={url.startsWith('/price-lists') ? 'page' : undefined}>Listas de precios</Link>
        </nav><p>Catálogo y precios,<br />siempre claros.</p></aside>
        <main id="main" className="workspace">
            {flash.success && <div key={flash.success + url} role="status" className="notice">{flash.success}</div>}
            {children}
        </main>
    </div>;
}
