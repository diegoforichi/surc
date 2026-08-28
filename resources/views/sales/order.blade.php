<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden {{ $order->number }}</title>
    <style>
        @page { margin: 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 13px; margin: 16px 0 8px; }
        p { margin: 3px 0; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; text-align: left; }
        th { background: #f9fafb; }
        .right { text-align: right; }
        .totals { width: 46%; margin-left: auto; }
        .legend { margin-top: 24px; font-size: 10px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    @php
        $org = $order->organization_snapshot ?? [];
        $client = $order->client_snapshot ?? [];
        $subject = $order->subject_snapshot ?? [];
        $deposit = $order->deposit_reference;
    @endphp

    <h1>Orden de venta {{ $order->number }}</h1>
    <p><strong>Fecha:</strong> {{ $order->issued_at?->format('d/m/Y H:i') ?? '—' }}</p>
    <p><strong>{{ terminology('organization', 'Sede') }}:</strong> {{ $org['issuer_name'] ?? $org['name'] ?? '—' }}</p>
    @if (! empty($org['issuer_document']))
        <p><strong>Documento emisor:</strong> {{ $org['issuer_document'] }}</p>
    @endif
    @if (! empty($org['issuer_address']) || ! empty($org['address']))
        <p><strong>Domicilio:</strong> {{ $org['issuer_address'] ?? $org['address'] }}</p>
    @endif

    <h2>{{ terminology('client', 'Cliente') }}</h2>
    <p>{{ $client['display_name'] ?? '—' }}
        @if (! empty($client['document_id']))
            ({{ $client['document_id'] }})
        @endif
    </p>

    <h2>{{ terminology('subject', 'Sujeto') }}</h2>
    <p>{{ $subject['label_name'] ?? '—' }}@if (! empty($subject['code'])) ({{ $subject['code'] }}) @endif</p>

    @if (is_array($deposit) && isset($deposit['amount']))
        <p class="muted">Seña confirmada del caso {{ $deposit['case_code'] ?? '' }}:
            {{ $deposit['currency'] ?? $order->currency }}
            {{ number_format((float) $deposit['amount'], 2, ',', '.') }}
            (informativa; no es una línea de esta orden).
        </p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Descripción</th>
                <th class="right">Cant.</th>
                <th>Unidad</th>
                <th class="right">Precio</th>
                <th class="right">Imp. %</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->lines as $line)
                <tr>
                    <td>{{ $line->code ?: '—' }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="right">{{ $line->quantity }}</td>
                    <td>{{ $line->unit }}</td>
                    <td class="right">{{ number_format((float) $line->unit_price, 2, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $line->tax_rate, 2, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $line->line_total, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><th>Subtotal</th><td class="right">{{ number_format((float) $order->subtotal, 2, ',', '.') }} {{ $order->currency }}</td></tr>
        <tr><th>Impuestos</th><td class="right">{{ number_format((float) $order->tax_total, 2, ',', '.') }} {{ $order->currency }}</td></tr>
        <tr><th>Total</th><td class="right">{{ number_format((float) $order->total, 2, ',', '.') }} {{ $order->currency }}</td></tr>
    </table>

    <p class="legend">Documento interno para facturar en el ERP. No es un comprobante fiscal. Identificador estable: {{ $order->export_uid }}. Emitida por {{ $issuedBy }} el {{ $issuedAt->format('d/m/Y H:i') }}.</p>
</body>
</html>
