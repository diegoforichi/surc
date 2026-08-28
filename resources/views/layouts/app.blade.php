<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SURC' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 text-gray-900">
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="{{ url('/admin') }}" class="font-semibold text-lg">SURC</a>
            <div class="space-x-4 text-sm">
                <a href="{{ url('/admin') }}" class="text-gray-600 hover:text-gray-900">Panel</a>
                @auth
                    <span>{{ auth()->user()->name }}</span>
                @endauth
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-6">
        @if (session('message'))
            <div class="mb-4 rounded bg-green-50 text-green-800 px-4 py-3">{{ session('message') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded bg-red-50 text-red-800 px-4 py-3">{{ session('error') }}</div>
        @endif

        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
