import { Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import Field from '../../components/Field';
import Layout from '../../components/Layout';
import type { Currency, PriceList, Quote, QuoteItem } from '../../types';

type CustomerChoice = { id: number; name: string; legal_name: string | null; tax_id: string | null };
type ProductChoice = { id: number; sku: string; name: string; unit: string; unit_label: string; display_price: string; currency: Currency; source: 'base_price' | 'price_list'; price_list_name: string | null };
type DraftItem = { id?: number; product_id: number; sku: string; name: string; unit_label: string; display_unit_price: string; price_source: 'base_price' | 'price_list'; price_list_name: string | null; quantity: string; discount_percent: string; tax_percent: string };
type DraftData = { customer_id: number | ''; price_list_id: number | ''; currency: Currency; issued_at: string; expires_at: string; notes: string; terms: string; items: DraftItem[] };
type PreviewLine = { display_unit_price: string; display_discount_amount: string; display_tax_amount: string; display_subtotal: string; display_total: string };
type Preview = { lines: PreviewLine[]; display_subtotal: string; display_discount_total: string; display_tax_total: string; display_total: string };
type Props = { quote: Quote | null; initialCustomer: CustomerChoice | null; priceLists: PriceList[]; today: string; defaultExpiry: string };

function existingItem(item: QuoteItem): DraftItem {
    return { id: item.id, product_id: item.product_id!, sku: item.sku_snapshot, name: item.name_snapshot, unit_label: item.unit_label, display_unit_price: item.display_unit_price, price_source: item.price_source, price_list_name: item.source_price_list_name_snapshot, quantity: item.quantity, discount_percent: item.discount_percent, tax_percent: item.tax_percent };
}

async function jsonGet<T>(url: string, signal?: AbortSignal): Promise<T> {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, signal });
    if (!response.ok) throw new Error('No pudimos completar la búsqueda.');
    return response.json() as Promise<T>;
}

export default function QuoteForm({ quote, initialCustomer, priceLists, today, defaultExpiry }: Props) {
    const form = useForm<DraftData>({
        customer_id: initialCustomer?.id ?? '', price_list_id: quote?.price_list_id ?? '', currency: quote?.currency ?? 'USD',
        issued_at: quote?.issued_at ?? today, expires_at: quote?.expires_at ?? defaultExpiry, notes: quote?.notes ?? '', terms: quote?.terms ?? '',
        items: quote?.items.map(existingItem) ?? [],
    });
    const [customer, setCustomer] = useState<CustomerChoice | null>(initialCustomer);
    const [customerQuery, setCustomerQuery] = useState(initialCustomer?.name ?? '');
    const [customerResults, setCustomerResults] = useState<CustomerChoice[]>([]);
    const [productQuery, setProductQuery] = useState('');
    const [productResults, setProductResults] = useState<ProductChoice[]>([]);
    const [lookupError, setLookupError] = useState('');
    const [preview, setPreview] = useState<Preview | null>(quote ? { lines: quote.items.map(item => ({ display_unit_price: item.display_unit_price, display_discount_amount: item.display_discount_amount, display_tax_amount: item.display_tax_amount, display_subtotal: item.display_line_subtotal, display_total: item.display_line_total })), display_subtotal: quote.display_subtotal, display_discount_total: quote.display_discount_total, display_tax_total: quote.display_tax_total, display_total: quote.display_total } : null);
    const [previewState, setPreviewState] = useState<'idle' | 'loading' | 'error'>('idle');
    const selectedList = priceLists.find(list => list.id === Number(form.data.price_list_id));

    useEffect(() => {
        if (customerQuery.trim().length < 1 || customer?.name === customerQuery) { setCustomerResults([]); return; }
        const controller = new AbortController(); const timer = window.setTimeout(() => {
            jsonGet<CustomerChoice[]>('/quotes/customers/search?q=' + encodeURIComponent(customerQuery), controller.signal).then(setCustomerResults).catch(error => { if (error.name !== 'AbortError') setLookupError(error.message); });
        }, 180);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [customerQuery, customer]);

    useEffect(() => {
        if (productQuery.trim().length < 1) { setProductResults([]); return; }
        const controller = new AbortController(); const timer = window.setTimeout(() => {
            const params = new URLSearchParams({ q: productQuery, currency: form.data.currency });
            if (form.data.price_list_id) params.set('price_list_id', String(form.data.price_list_id));
            jsonGet<ProductChoice[]>('/quotes/products/search?' + params, controller.signal).then(setProductResults).catch(error => { if (error.name !== 'AbortError') setLookupError(error.message); });
        }, 180);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [productQuery, form.data.currency, form.data.price_list_id]);

    const previewPayload = useMemo(() => JSON.stringify({ customer_id: form.data.customer_id, price_list_id: form.data.price_list_id || null, currency: form.data.currency, issued_at: form.data.issued_at, expires_at: form.data.expires_at || null, notes: form.data.notes || null, terms: form.data.terms || null, items: form.data.items.map(item => ({ id: item.id, product_id: item.product_id, quantity: item.quantity, discount_percent: item.discount_percent, tax_percent: item.tax_percent })) }), [form.data]);
    useEffect(() => {
        if (!form.data.customer_id || form.data.items.length === 0) { setPreview(null); return; }
        const controller = new AbortController(); const timer = window.setTimeout(async () => {
            setPreviewState('loading');
            try {
                const response = await fetch(quote ? `/quotes/${quote.id}/preview` : '/quotes/preview', { method: 'POST', credentials: 'same-origin', signal: controller.signal, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' }, body: previewPayload });
                if (!response.ok) throw new Error('Revisa cantidades, descuentos e impuestos.');
                setPreview(await response.json() as Preview); setPreviewState('idle');
            } catch (error) { if ((error as Error).name !== 'AbortError') { setPreviewState('error'); setLookupError((error as Error).message); } }
        }, 220);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [previewPayload, quote, form.data.customer_id, form.data.items.length]);

    function chooseCustomer(choice: CustomerChoice) { setCustomer(choice); setCustomerQuery(choice.name); setCustomerResults([]); form.setData('customer_id', choice.id); }
    function addProduct(product: ProductChoice) {
        if (form.data.items.some(item => item.product_id === product.id)) { setLookupError('Ese producto ya está en la cotización.'); return; }
        form.setData('items', [...form.data.items, { product_id: product.id, sku: product.sku, name: product.name, unit_label: product.unit_label, display_unit_price: product.display_price, price_source: product.source, price_list_name: product.price_list_name, quantity: '1', discount_percent: '0', tax_percent: '0' }]);
        setProductQuery(''); setProductResults([]); setLookupError('');
    }
    function updateItem(index: number, field: 'quantity' | 'discount_percent' | 'tax_percent', value: string) { form.setData('items', form.data.items.map((item, itemIndex) => itemIndex === index ? { ...item, [field]: value } : item)); }
    function removeItem(index: number) { form.setData('items', form.data.items.filter((_, itemIndex) => itemIndex !== index)); }
    function changeList(value: string) { const list = priceLists.find(candidate => candidate.id === Number(value)); form.setData(data => ({ ...data, price_list_id: value ? Number(value) : '', currency: list?.currency ?? data.currency })); setProductQuery(''); }
    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform(data => ({ ...data, price_list_id: data.price_list_id || null, expires_at: data.expires_at || null, items: data.items.map(item => ({ id: item.id, product_id: item.product_id, quantity: item.quantity, discount_percent: item.discount_percent, tax_percent: item.tax_percent })) }) as never);
        const options = { onError: () => requestAnimationFrame(() => document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()) };
        if (quote) form.put('/quotes/' + quote.id, options); else form.post('/quotes', options);
    }
    const firstError = Object.values(form.errors)[0];

    return <Layout title={quote ? `Editar ${quote.number}` : 'Nueva cotización'}>
        <Link className="back-link" href={quote ? '/quotes/' + quote.id : '/quotes'}>Volver a {quote ? 'la cotización' : 'cotizaciones'}</Link>
        <div className="quote-editor-heading"><div><p className="eyebrow">{quote?.number ?? 'Nuevo borrador'}</p><h1>{quote ? 'Editar cotización' : 'Nueva cotización'}</h1><p>Selecciona, agrega y guarda. Los importes los confirma CotizaFlow.</p></div><div className="quote-progress"><strong>{form.data.items.length}</strong><span>{form.data.items.length === 1 ? 'producto' : 'productos'}</span></div></div>
        <form onSubmit={submit} className="quote-editor">
            {(firstError || lookupError) && <div className="form-alert" role="alert">{firstError || lookupError}</div>}
            <section className="quote-setup"><div className="customer-picker"><label htmlFor="customer-search">Cliente *</label><input id="customer-search" className="input" type="search" autoComplete="off" placeholder="Buscar por nombre, razón social o ID fiscal" value={customerQuery} aria-invalid={Boolean(form.errors.customer_id)} onChange={e => { setCustomerQuery(e.target.value); setCustomer(null); form.setData('customer_id', ''); }} />{customerResults.length > 0 && <div className="lookup-results" role="listbox" aria-label="Clientes encontrados">{customerResults.map(result => <button type="button" role="option" key={result.id} onClick={() => chooseCustomer(result)}><strong>{result.name}</strong><span>{result.legal_name || result.tax_id || 'Cliente activo'}</span></button>)}</div>}{customer && <p className="selection-confirmed">Cliente seleccionado: <strong>{customer.name}</strong></p>}</div>
                <div className="field"><label htmlFor="price-list">Lista de precios</label><select id="price-list" className="input" value={form.data.price_list_id} onChange={e => changeList(e.target.value)}><option value="">Precio base · {form.data.currency}</option>{priceLists.map(list => <option key={list.id} value={list.id} disabled={form.data.items.length > 0 && list.currency !== form.data.currency}>{list.name} · {list.currency}{list.status === 'inactive' ? ' · Inactiva' : ''}</option>)}</select><p className="field-note">Cambiar la lista no modifica líneas existentes.</p></div>
                <div className="field"><label htmlFor="currency">Moneda *</label><select id="currency" className="input" value={form.data.currency} disabled={form.data.items.length > 0 || Boolean(selectedList)} onChange={e => form.setData('currency', e.target.value as Currency)}>{(['USD', 'BOB', 'ARS'] as Currency[]).map(currency => <option key={currency}>{currency}</option>)}</select></div>
                <Field id="issued_at" name="issued_at" type="date" label="Fecha" required value={form.data.issued_at} error={form.errors.issued_at} onChange={e => form.setData('issued_at', e.target.value)} />
                <Field id="expires_at" name="expires_at" type="date" label="Vigencia" value={form.data.expires_at} error={form.errors.expires_at} onChange={e => form.setData('expires_at', e.target.value)} />
            </section>

            <section className="quote-builder"><div className="section-heading"><div><h2>Productos</h2><p>Busca por SKU o nombre. El precio se resuelve con {selectedList?.name ?? 'el precio base'}.</p></div></div>
                <div className="product-picker"><label htmlFor="product-search">Agregar producto</label><input id="product-search" className="input" type="search" autoComplete="off" placeholder="Escribe SKU o nombre" value={productQuery} onChange={e => setProductQuery(e.target.value)} />{productResults.length > 0 && <div className="lookup-results product-results" role="listbox" aria-label="Productos encontrados">{productResults.map(product => <button type="button" role="option" key={product.id} onClick={() => addProduct(product)}><span><b className="sku">{product.sku}</b><strong>{product.name}</strong><small>{product.unit_label} · {product.source === 'price_list' ? product.price_list_name : 'Precio base'}</small></span><b className="money">{product.currency} {product.display_price}</b></button>)}</div>}</div>
                {form.data.items.length === 0 ? <div className="quote-empty-lines"><h3>Agrega el primer producto</h3><p>Las cantidades y descuentos se editan aquí mismo.</p></div> : <div className="quote-line-editor"><div className="quote-line-head"><span>Producto</span><span>Cantidad</span><span>Precio</span><span>Desc. %</span><span>Impuesto %</span><span>Total</span><span></span></div>{form.data.items.map((item, index) => <article className="quote-line" key={item.id ?? item.product_id}><div><span className="sku">{item.sku}</span><strong>{item.name}</strong><small>{item.unit_label} · {item.price_source === 'price_list' ? item.price_list_name : 'Precio base'}</small></div><div><label className="mobile-field-label" htmlFor={`quantity-${index}`}>Cantidad</label><span className="sr-only">Cantidad de {item.name}</span><input id={`quantity-${index}`} aria-label={`Cantidad de ${item.name}`} className="input money" inputMode="decimal" value={item.quantity} aria-invalid={Boolean(form.errors[`items.${index}.quantity` as never])} onChange={e => updateItem(index, 'quantity', e.target.value)} /></div><span className="money">{form.data.currency} {preview?.lines[index]?.display_unit_price ?? item.display_unit_price}</span><div><label className="mobile-field-label" htmlFor={`discount-${index}`}>Descuento %</label><span className="sr-only">Descuento de {item.name}</span><input id={`discount-${index}`} aria-label={`Descuento de ${item.name}`} className="input money" inputMode="decimal" value={item.discount_percent} onChange={e => updateItem(index, 'discount_percent', e.target.value)} /></div><div><label className="mobile-field-label" htmlFor={`tax-${index}`}>Impuesto %</label><span className="sr-only">Impuesto de {item.name}</span><input id={`tax-${index}`} aria-label={`Impuesto de ${item.name}`} className="input money" inputMode="decimal" value={item.tax_percent} onChange={e => updateItem(index, 'tax_percent', e.target.value)} /></div><strong className="money">{preview ? `${form.data.currency} ${preview.lines[index]?.display_total ?? '—'}` : '—'}</strong><button type="button" className="remove-line" aria-label={`Quitar ${item.name}`} onClick={() => removeItem(index)}>Quitar</button></article>)}</div>}
            </section>

            <div className="quote-bottom"><section className="quote-notes"><Field id="notes" name="notes" label="Observaciones" multiline maxLength={5000} value={form.data.notes} error={form.errors.notes} onChange={e => form.setData('notes', e.target.value)} /><Field id="terms" name="terms" label="Condiciones comerciales" multiline maxLength={5000} value={form.data.terms} error={form.errors.terms} onChange={e => form.setData('terms', e.target.value)} /></section><aside className="live-total" aria-live="polite"><p>{previewState === 'loading' ? 'Confirmando importes…' : previewState === 'error' ? 'Revisa los valores' : 'Totales confirmados por servidor'}</p><dl><div><dt>Subtotal</dt><dd>{form.data.currency} {preview?.display_subtotal ?? '—'}</dd></div><div><dt>Descuento</dt><dd>− {form.data.currency} {preview?.display_discount_total ?? '—'}</dd></div><div><dt>Impuestos</dt><dd>{form.data.currency} {preview?.display_tax_total ?? '—'}</dd></div><div className="grand-total"><dt>Total</dt><dd>{form.data.currency} {preview?.display_total ?? '—'}</dd></div></dl><button className="button primary save-quote" disabled={form.processing || previewState === 'loading' || !preview}>{form.processing ? 'Guardando…' : 'Guardar borrador'}</button></aside></div>
        </form>
    </Layout>;
}
