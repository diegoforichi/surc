<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe caso {{ $case->code ?? $case->id }}</title>
    <style>
        @page { margin: 20mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1, h2 { margin: 0 0 8px 0; }
        h1 { font-size: 18px; }
        h2 { font-size: 14px; margin-top: 16px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        p { margin: 4px 0; }
        .muted { color: #6b7280; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td, .grid th { border: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        .grid th { background: #f9fafb; text-align: left; }
        .list { margin: 6px 0 0 18px; }
    </style>
</head>
<body>
    <h1>Informe del caso</h1>
    <p><strong>{{ terminology('organization', 'Sede') }}:</strong> {{ $case->organization?->name ?? '—' }}</p>
    <p><strong>{{ terminology('case', 'Caso') }}:</strong> {{ $case->title }}</p>
    <p><strong>Código:</strong> {{ $case->code ?? $case->id }}</p>
    <p><strong>Estado:</strong> {{ \App\Support\Cases\CaseStatusDisplay::label($case->status) }}</p>
    <p><strong>Emitido:</strong> {{ now()->format('d/m/Y H:i') }}</p>

    <h2>Datos generales</h2>
    <table class="grid">
        <tr>
            <th>{{ terminology('subject', 'Sujeto') }}</th>
            <td>{{ $case->subject?->label_name ?? '—' }}</td>
            <th>{{ terminology('client', 'Cliente') }}</th>
            <td>{{ $case->subject?->owner?->display_name ?? '—' }}</td>
        </tr>
        <tr>
            <th>Etapa actual</th>
            <td>{{ $case->currentStage?->label ?? '—' }}</td>
            <th>Turno</th>
            <td>{{ $case->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <th>{{ terminology('agenda', 'Agenda') }}</th>
            <td>{{ $case->agenda?->scheduled_date?->format('d/m/Y') ?? '—' }}</td>
            <th>{{ terminology('specialist', 'Especialista') }}</th>
            <td>{{ $case->agenda?->specialist?->display_name ?? '—' }}</td>
        </tr>
    </table>

    <h2>Ficha resumida</h2>
    <p>{{ $case->summary ?: 'Sin resumen cargado.' }}</p>

    @if ($fieldDefinitions->isNotEmpty())
        <table class="grid">
            <tr>
                <th>Campo</th>
                <th>Valor</th>
            </tr>
            @foreach ($fieldDefinitions as $definition)
                <tr>
                    <td>{{ $definition->label }}</td>
                    <td>{{ data_get($case->metadata, $definition->key, '—') ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @php($lastConsultation = $case->events->firstWhere('type', 'consultation'))
    <h2>Consulta</h2>
    <p><strong>{{ terminology('ux.case_diagnosis', 'Hallazgos') }}:</strong> {{ data_get($case->metadata, 'diagnosis', '—') ?: '—' }}</p>
    <p><strong>{{ terminology('ux.case_treatment', 'Trabajo a realizar') }}:</strong> {{ data_get($case->metadata, 'treatment', '—') ?: '—' }}</p>
    <p><strong>Responsable interviniente:</strong>
        {{ $lastConsultation?->technicalResponsible?->display_name ?? $lastConsultation?->technical_responsible_name ?? '—' }}
    </p>
    <p><strong>Notas:</strong> {{ $lastConsultation?->description ?? '—' }}</p>

    <h2>Pagos</h2>
    @if ($case->payments->isEmpty())
        <p class="muted">Sin pagos registrados.</p>
    @else
        <table class="grid">
            <tr>
                <th>Tipo</th>
                <th>Monto</th>
                <th>Método</th>
                <th>Estado</th>
            </tr>
            @foreach ($case->payments as $payment)
                <tr>
                    <td>{{ \App\Support\Labels\OperationalStatusLabels::paymentType($payment->type) }}</td>
                    <td>${{ number_format((float) $payment->amount, 2) }}</td>
                    <td>{{ $payment->method ?? '—' }}</td>
                    <td>{{ \App\Support\Labels\OperationalStatusLabels::payment($payment->status) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Archivos adjuntos</h2>
    @php($attachments = $case->getMedia('attachments'))
    @if ($attachments->isEmpty())
        <p class="muted">Sin archivos adjuntos.</p>
    @else
        <ul class="list">
            @foreach ($attachments as $media)
                <li>{{ $media->file_name }}</li>
            @endforeach
        </ul>
    @endif

    <h2>Eventos y auditoría</h2>
    @if ($case->events->isEmpty())
        <p class="muted">Sin eventos registrados.</p>
    @else
        <ul class="list">
            @foreach ($case->events as $event)
                <li>
                    {{ \App\Support\Cases\CaseEventLabels::label($event->type) }}
                    — {{ $event->description ?? 'Sin detalle' }}
                    ({{ $event->author?->name ?? 'Sistema' }} · {{ $event->created_at?->format('d/m/Y H:i') }})
                </li>
            @endforeach
        </ul>
    @endif
</body>
</html>
