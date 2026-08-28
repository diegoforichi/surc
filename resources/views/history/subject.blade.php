<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('history.styles')
</head>
<body>
    @php
        $lastWeight = $lastWeight ?? null;
        $upcoming = $upcoming ?? [];
    @endphp
    @include('history.header')

    <table class="grid">
        <tr>
            <th>Último peso</th>
            <td>{{ $lastWeight ?? '—' }}</td>
        </tr>
        <tr>
            <th>Próximos controles</th>
            <td>
                @if (empty($upcoming))
                    —
                @else
                    @foreach ($upcoming as $event)
                        {{ $event['date']?->format('d/m/Y') }} — {{ $event['label'] }}{{ $event['summary'] ? ': '.$event['summary'] : '' }}<br>
                    @endforeach
                @endif
            </td>
        </tr>
    </table>

    <p class="muted">Incluye solo registros finales de esta sede. Las adendas aparecen debajo del registro original. No se incluyen borradores ni archivos binarios.</p>

    @include('history.entries', ['entries' => $entries])

    @include('history.footer')
</body>
</html>
