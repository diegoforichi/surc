<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('history.styles')
</head>
<body>
    @include('history.header')

    <p class="muted">Incluye solo registros finales de esta sede. Las adendas aparecen debajo del registro original. No se incluyen borradores ni archivos binarios.</p>

    @include('history.entries', ['entries' => $entries])

    @include('history.footer')
</body>
</html>
