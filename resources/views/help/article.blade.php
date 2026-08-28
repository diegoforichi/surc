<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 8px 0; }
        h2, h3, h4 { font-size: 14px; margin: 14px 0 6px 0; }
        p, li { margin: 4px 0; line-height: 1.4; }
        ul, ol { margin: 6px 0 6px 18px; padding: 0; }
        .muted { color: #6b7280; font-size: 11px; }
        .excerpt { margin: 0 0 12px 0; }
        .legend { margin-top: 24px; font-size: 10px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    <p class="muted">{{ $networkName }} — Capacitación interna</p>
    <h1>{{ $title }}</h1>
    @if ($excerpt)
        <p class="excerpt">{{ $excerpt }}</p>
    @endif

    {!! $body !!}

    <p class="legend">
        Emitido por {{ $issuedBy }} el {{ $issuedAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
        Guía operativa del panel; no incluye datos clínicos ni credenciales.
    </p>
</body>
</html>
