import { Head, useForm } from '@inertiajs/react';
import Field from '../../components/Field';
export default function Login() {
    const form = useForm({ email: '', password: '' });
    return <main className="auth-page"><Head title="Iniciar sesión" /><div className="auth-panel"><div className="brand">Cotiza<span>Flow</span></div><h1>Bienvenido de nuevo.</h1><p className="muted">Ingresa para trabajar con tus clientes.</p>
        <form onSubmit={e => { e.preventDefault(); form.post('/login', { onFinish: () => form.reset('password') }); }}>
            <Field id="email" label="Email" type="email" autoComplete="username" required autoFocus value={form.data.email} onChange={e => form.setData('email', e.target.value)} error={form.errors.email} />
            <Field id="password" label="Contraseña" type="password" autoComplete="current-password" required value={form.data.password} onChange={e => form.setData('password', e.target.value)} error={form.errors.password} />
            <button className="button primary" disabled={form.processing}>{form.processing ? 'Ingresando…' : 'Iniciar sesión'}</button>
        </form></div></main>;
}
