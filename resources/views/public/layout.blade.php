<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $network->name)</title>
    @php
        $resolvedSeo = $seoDescription ?? $network->seoDescription();
    @endphp
    @if (! empty($resolvedSeo))
        <meta name="description" content="{{ $resolvedSeo }}">
    @endif
    @vite(['resources/css/app.css'])
    <style>:root { --primary: {{ $network->primary_color ?? '#0f766e' }}; }</style>
</head>
<body class="bg-slate-50 text-slate-900">
    <header class="border-b bg-white" style="border-color: var(--primary)">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-4">
            <a href="{{ route('public.home', $network->slug) }}" class="flex items-center gap-3">
                @if ($network->logo_path)
                    <img src="{{ asset('storage/'.$network->logo_path) }}" alt="" class="h-10 w-10 object-contain">
                @endif
                <span class="text-xl font-bold" style="color: var(--primary)">{{ $network->name }}</span>
            </a>
            <button type="button" class="rounded border px-3 py-1 text-sm md:hidden" data-nav-toggle aria-expanded="false">Menú</button>
            <nav id="public-nav" class="hidden w-full flex-col gap-3 text-sm md:flex md:w-auto md:flex-row md:items-center md:gap-5">
                <a href="{{ route('public.organizations', $network->slug) }}" class="{{ request()->routeIs('public.organizations', 'public.organization') ? 'font-semibold text-teal-800 underline' : 'hover:underline' }}">{{ terminology('organization', 'Sedes') }}</a>
                <a href="{{ route('public.specialists', $network->slug) }}" class="{{ request()->routeIs('public.specialists', 'public.specialist') ? 'font-semibold text-teal-800 underline' : 'hover:underline' }}">{{ terminology('specialist', 'Especialistas') }}</a>
                <a href="{{ route('public.posts', $network->slug) }}" class="{{ request()->routeIs('public.posts', 'public.post') ? 'font-semibold text-teal-800 underline' : 'hover:underline' }}">Blog</a>
                <a href="{{ route('public.help', $network->slug) }}" class="{{ request()->routeIs('public.help', 'public.page') ? 'font-semibold text-teal-800 underline' : 'hover:underline' }}">Ayuda</a>
                @auth
                    <a href="{{ url('/admin/capacitacion') }}" class="hover:underline">Capacitación</a>
                    <a href="{{ url('/admin') }}" class="hover:underline">Panel</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        @yield('content')
    </main>

    <footer class="mt-12 border-t bg-white">
        <div class="mx-auto flex max-w-5xl justify-between px-4 py-6 text-sm text-slate-500">
            <span>{{ $network->name }}</span>
            <a href="{{ url('/admin') }}">Acceso SURC</a>
        </div>
    </footer>
    <script>
        document.querySelector('[data-nav-toggle]')?.addEventListener('click', function () {
            const nav = document.getElementById('public-nav');
            const open = ! nav.classList.contains('hidden');
            nav.classList.toggle('hidden', open);
            nav.classList.toggle('flex', ! open);
            this.setAttribute('aria-expanded', open ? 'false' : 'true');
        });
    </script>
</body>
</html>
