import { Link } from '@inertiajs/react';
import Layout from '../../components/Layout';

export default function Dashboard() {
    return <Layout title="Dashboard">
        <div className="dashboard-start"><p className="eyebrow">Inicio comercial</p><h1>Cotiza con menos pasos.</h1><p>Selecciona un cliente, agrega productos y guarda una propuesta con importes confirmados por CotizaFlow.</p><div className="row-actions"><Link href="/quotes/create" className="button primary">Nueva cotización</Link><Link href="/quotes" className="button">Ver cotizaciones</Link></div></div>
        <div className="dashboard-links"><Link href="/customers"><span>01</span><strong>Clientes</strong><p>Busca o registra a quien recibirá la propuesta.</p></Link><Link href="/products"><span>02</span><strong>Productos</strong><p>Revisa catálogo, unidades y precios disponibles.</p></Link><Link href="/price-lists"><span>03</span><strong>Listas de precios</strong><p>Confirma los precios comerciales antes de cotizar.</p></Link></div>
    </Layout>;
}
