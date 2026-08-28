<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Constancia {{ $case->code ?? $case->id }}</title>
    <style>
        @page { margin: 4mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; width: 72mm; margin: 0 auto; color: #111; }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 6px 0; }
        .muted { color: #444; font-size: 10px; }
        .box { border: 1px solid #000; padding: 6px; margin: 6px 0; }
        .sign { height: 28px; border-bottom: 1px solid #000; margin-top: 16px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    @php
        $instructions = \App\Support\Cases\ConstancyContent::instructions($case);
        $consent = \App\Support\Cases\ConstancyContent::consent($case);
        $when = \App\Support\Cases\ConstancyContent::scheduledLabel($case);
    @endphp

    <div class="no-print" style="margin-bottom:8px;">
        <button onclick="window.print()">Imprimir</button>
        <a href="{{ route('cases.ticket.pdf', $case) }}">Descargar PDF</a>
    </div>

    <div class="center">
        <strong>{{ $case->organization?->network?->name ?? 'SURC' }}</strong><br>
        {{ $case->organization?->name }}<br>
        <span class="muted">{{ now()->format('d/m/Y H:i') }}</span>
    </div>

    <div class="line"></div>

    <p><strong>{{ terminology('case', 'Caso') }}:</strong> {{ $case->title }}</p>
    <p><strong>Código:</strong> {{ $case->code ?? $case->id }}</p>
    @if ($case->agenda_order)
        <p><strong>Orden:</strong> {{ $case->agenda_order }}</p>
    @endif
    @if ($case->subject)
        <p><strong>{{ terminology('subject', 'Sujeto') }}:</strong> {{ $case->subject->label_name }}
            @if ($case->subject->code) ({{ $case->subject->code }}) @endif
        </p>
        @if ($case->subject->owner)
            <p><strong>{{ terminology('client', 'Responsable') }}:</strong> {{ $case->subject->owner->display_name }}</p>
        @endif
    @endif

    @if ($case->agenda)
        @if ($case->agenda->organization && $case->agenda->organization_id !== $case->organization_id)
            <p><strong>Se atiende en:</strong> {{ $case->agenda->organization->name }}</p>
        @endif
        <p><strong>Fecha:</strong> {{ $case->agenda->scheduled_date?->format('d/m/Y') ?? '—' }}</p>
        <p><strong>Turno:</strong> {{ $when }}</p>
        @if ($case->agenda->title)
            <p><strong>Servicio:</strong> {{ $case->agenda->title }}</p>
        @endif
        @if ($case->agenda->specialist)
            <p><strong>{{ terminology('specialist', 'Especialista') }}:</strong> {{ $case->agenda->specialist->display_name }}</p>
        @endif
    @else
        <p><strong>Turno:</strong> {{ $when }}</p>
    @endif

    @if ($instructions !== '')
        <div class="line"></div>
        <p><strong>Indicaciones</strong></p>
        <div class="box">{!! nl2br(e($instructions)) !!}</div>
    @endif

    @if ($consent !== '')
        <p><strong>Consentimiento</strong></p>
        <div class="box">{!! nl2br(e($consent)) !!}</div>
        <p class="muted">Declaro haber leído y aceptado las indicaciones.</p>
        <div class="sign"></div>
        <p class="muted">Firma y aclaración</p>
    @endif

    <div class="line"></div>
    <p class="center muted">Conserve este comprobante. Emitido por {{ auth()->user()?->name ?? 'SURC' }}.</p>
</body>
</html>
