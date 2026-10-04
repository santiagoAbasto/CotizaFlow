<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22mm 16mm 20mm; }
        * { box-sizing: border-box; }
        body { color: #202124; font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; line-height: 1.45; }
        h1, h2, p { margin: 0; }
        .watermark { color: #d6d9e2; font-size: 44pt; font-weight: bold; left: 0; position: fixed; right: 0; text-align: center; top: 43%; transform: rotate(-28deg); z-index: -1; }
        .header { border-bottom: 2px solid #002fa7; padding-bottom: 14px; }
        .brand { color: #002fa7; font-size: 19pt; font-weight: bold; }
        .company { color: #62646c; margin-top: 3px; }
        .document-title { float: right; text-align: right; }
        .document-title h1 { font-size: 18pt; letter-spacing: .02em; }
        .document-title p { color: #62646c; margin-top: 3px; }
        .clear { clear: both; }
        .parties { margin: 22px 0 18px; width: 100%; }
        .party { vertical-align: top; width: 50%; }
        .party + .party { padding-left: 20px; }
        .label { color: #62646c; font-size: 7.5pt; font-weight: bold; letter-spacing: .08em; text-transform: uppercase; }
        .party strong { display: block; font-size: 11pt; margin: 5px 0 2px; }
        .party p { color: #4d4d55; margin-top: 2px; }
        table { border-collapse: collapse; width: 100%; }
        thead { display: table-header-group; }
        th { background: #f1f3f8; border-bottom: 1px solid #c9ceda; color: #4d4d55; font-size: 7.5pt; letter-spacing: .04em; padding: 8px 6px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #e3e5e9; padding: 9px 6px; vertical-align: top; }
        tr { page-break-inside: avoid; }
        .sku { color: #62646c; display: block; font-size: 7.5pt; font-weight: bold; letter-spacing: .04em; margin-bottom: 2px; }
        .description { color: #62646c; display: block; font-size: 8.5pt; margin-top: 2px; }
        .right { text-align: right; white-space: nowrap; }
        .totals { margin-left: auto; margin-top: 18px; width: 245px; }
        .totals td { border: 0; padding: 4px 0; }
        .totals .total td { border-top: 1px solid #202124; font-size: 13pt; font-weight: bold; padding-top: 10px; }
        .copy { border-top: 1px solid #d7d9df; margin-top: 28px; padding-top: 16px; }
        .copy section { margin-bottom: 15px; }
        .copy h2 { font-size: 9pt; margin-bottom: 5px; }
        .copy p { color: #4d4d55; white-space: pre-wrap; }
        .footer { bottom: -12mm; color: #62646c; font-size: 7.5pt; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    @if ($isDraft)
        <div class="watermark">BORRADOR</div>
    @endif
    <header class="header">
        <div class="document-title">
            <h1>COTIZACIÓN</h1>
            <p>{{ $quote->number }} · {{ $quote->status->label() }}</p>
        </div>
        <div class="brand">CotizaFlow</div>
        <p class="company">{{ $companyName }}</p>
        <div class="clear"></div>
    </header>

    <table class="parties">
        <tr>
            <td class="party">
                <span class="label">Empresa vendedora</span>
                <strong>{{ $companyName }}</strong>
            </td>
            <td class="party">
                <span class="label">Cliente</span>
                <strong>{{ $quote->customer_name_snapshot }}</strong>
                @if ($quote->customer_legal_name_snapshot)<p>{{ $quote->customer_legal_name_snapshot }}</p>@endif
                @if ($quote->customer_tax_id_snapshot)<p>ID fiscal: {{ $quote->customer_tax_id_snapshot }}</p>@endif
                @if ($quote->customer_email_snapshot)<p>{{ $quote->customer_email_snapshot }}</p>@endif
                @if ($quote->customer_phone_snapshot)<p>{{ $quote->customer_phone_snapshot }}</p>@endif
            </td>
        </tr>
        <tr>
            <td class="party">
                <span class="label">Fecha</span>
                <strong>{{ $quote->issued_at->format('d/m/Y') }}</strong>
            </td>
            <td class="party">
                <span class="label">Vigencia</span>
                <strong>{{ $quote->expires_at?->format('d/m/Y') ?? 'Sin vencimiento' }}</strong>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="right">Cant.</th>
                <th class="right">Precio unit.</th>
                <th class="right">Desc.</th>
                <th class="right">Imp.</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>
                        <span class="sku">{{ $item->sku_snapshot }}</span>
                        <strong>{{ $item->name_snapshot }}</strong>
                        @if ($item->description_snapshot)<span class="description">{{ $item->description_snapshot }}</span>@endif
                        <span class="description">{{ $item->unit_snapshot->label() }} · {{ $item->price_source->value === 'price_list' ? $item->source_price_list_name_snapshot : 'Precio base' }}</span>
                    </td>
                    <td class="right">{{ rtrim(rtrim($item->quantity, '0'), '.') ?: '0' }}</td>
                    <td class="right">{{ $quote->currency->value }} {{ $quote->currency->format($item->unit_price) }}</td>
                    <td class="right">{{ rtrim(rtrim($item->discount_percent, '0'), '.') ?: '0' }}%</td>
                    <td class="right">{{ rtrim(rtrim($item->tax_percent, '0'), '.') ?: '0' }}%</td>
                    <td class="right">{{ $quote->currency->value }} {{ $quote->currency->format($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ $quote->currency->value }} {{ $quote->currency->format($quote->subtotal) }}</td></tr>
        <tr><td>Descuento</td><td class="right">− {{ $quote->currency->value }} {{ $quote->currency->format($quote->discount_total) }}</td></tr>
        <tr><td>Impuestos</td><td class="right">{{ $quote->currency->value }} {{ $quote->currency->format($quote->tax_total) }}</td></tr>
        <tr class="total"><td>TOTAL</td><td class="right">{{ $quote->currency->value }} {{ $quote->currency->format($quote->total) }}</td></tr>
    </table>

    @if ($quote->notes || $quote->terms)
        <div class="copy">
            @if ($quote->notes)<section><h2>Observaciones</h2><p>{{ $quote->notes }}</p></section>@endif
            @if ($quote->terms)<section><h2>Condiciones</h2><p>{{ $quote->terms }}</p></section>@endif
        </div>
    @endif

    <div class="footer">{{ $quote->number }} · {{ $companyName }}</div>
</body>
</html>
