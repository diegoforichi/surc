<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('history.styles')
</head>
<body>
    @include('history.header')

    @include('history.entries', ['entries' => $entries])

    @include('history.footer')
</body>
</html>
