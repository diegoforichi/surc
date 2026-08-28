@extends('public.layout', ['seoDescription' => $network->seoDescription()])

@section('title', $network->name)

@section('content')
    <section class="mb-8 overflow-hidden rounded-2xl bg-white shadow-sm" style="border-top: 6px solid var(--primary)">
        <style>
            .network-hero {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 6.5rem;
                grid-template-areas: "intro cover" "details details";
                gap: 0.75rem 0.9rem;
                padding: 1.1rem 1.15rem 1.2rem;
            }
            .network-intro { grid-area: intro; min-width: 0; }
            .network-cover { grid-area: cover; width: 6.5rem; height: 6.5rem; border-radius: 0.75rem; object-fit: cover; }
            .network-details { grid-area: details; min-width: 0; }
            .network-hero--text {
                grid-template-columns: minmax(0, 1fr);
                grid-template-areas: "intro" "details";
            }
            @media (min-width: 768px) {
                .network-hero {
                    gap: 0.85rem 1.5rem;
                    padding: 1.5rem;
                    align-items: start;
                }
                .network-hero:not(.network-hero--text) {
                    grid-template-columns: minmax(0, 1fr) 12.5rem;
                    grid-template-areas: "intro cover" "details cover";
                }
                .network-cover { width: 12.5rem; height: 10rem; }
            }
        </style>
        <div class="network-hero{{ $network->cover_path ? '' : ' network-hero--text' }}">
            <div class="network-intro">
                <p class="text-sm uppercase tracking-wide text-slate-500">Red de {{ terminology('organization', 'sedes') }}</p>
                <h1 class="mt-1 text-2xl font-bold md:text-3xl" style="color: var(--primary)">{{ $network->name }}</h1>
                @if ($network->slogan)
                    <p class="mt-2 text-base font-medium text-slate-700 md:text-lg">{{ $network->slogan }}</p>
                @endif
            </div>
            @if ($network->cover_path)
                <img src="{{ asset('storage/'.$network->cover_path) }}" alt="" class="network-cover">
            @endif
            <div class="network-details">
                @if ($network->description)
                    <p class="max-w-xl whitespace-pre-line text-sm text-slate-600 md:text-base">{{ $network->description }}</p>
                @endif
                <div class="mt-3 space-y-1 text-sm text-slate-600">
                    @if ($network->phone)
                        <p>Teléfono: {{ $network->phone }}</p>
                    @endif
                    @if ($network->email)
                        <p>Correo: <a class="text-teal-800 underline" href="mailto:{{ $network->email }}">{{ $network->email }}</a></p>
                    @endif
                    @if ($network->address)
                        <p>{{ $network->address }}</p>
                    @endif
                </div>
                @if ($network->whatsappUrl())
                    <a class="mt-4 inline-block rounded-lg px-4 py-2 text-sm text-white" style="background: var(--primary)" href="{{ $network->whatsappUrl() }}" target="_blank" rel="noopener">WhatsApp</a>
                @endif
            </div>
        </div>
    </section>

    @if ($carousel->isNotEmpty())
        <section class="mb-10">
            <h2 class="mb-4 text-xl font-semibold">Novedades</h2>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($carousel as $item)
                    <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <x-public.media :path="$item->image_path" :alt="$item->title" />
                        <div class="p-4">
                            <h3 class="font-medium">{{ $item->title }}</h3>
                            <div class="mt-2 text-sm text-slate-600">{!! \App\Support\Html\SafeHtml::render($item->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($item->body), 160)) !!}</div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mb-10">
        <div class="mb-4 flex items-end justify-between gap-4">
            <h2 class="text-xl font-semibold">{{ terminology('organization', 'Sedes') }}</h2>
            @if ($organizationsTotal > $organizations->count())
                <a href="{{ route('public.organizations', $network->slug) }}" class="text-sm text-teal-800 hover:underline">Ver todas</a>
            @endif
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            @forelse ($organizations as $org)
                <x-public.organization-card :organization="$org" :network="$network" />
            @empty
                <p class="text-sm text-slate-500">Todavía no hay sedes publicadas.</p>
            @endforelse
        </div>
    </section>

    <section class="mb-10">
        <div class="mb-4 flex items-end justify-between gap-4">
            <h2 class="text-xl font-semibold">{{ terminology('specialist', 'Especialistas') }}</h2>
            @if ($specialistsTotal > $specialists->count())
                <a href="{{ route('public.specialists', $network->slug) }}" class="text-sm text-teal-800 hover:underline">Ver todos</a>
            @endif
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            @forelse ($specialists as $specialist)
                <x-public.specialist-card :specialist="$specialist" :network="$network" />
            @empty
                <p class="text-sm text-slate-500">Todavía no hay perfiles publicados. Esta sección se actualizará cuando la red cargue especialistas en el directorio.</p>
            @endforelse
        </div>
    </section>

    <section class="mb-10">
        <div class="mb-4 flex items-end justify-between gap-4">
            <h2 class="text-xl font-semibold">Blog</h2>
            @if ($postsTotal > $posts->count())
                <a href="{{ route('public.posts', $network->slug) }}" class="text-sm text-teal-800 hover:underline">Ver todas</a>
            @endif
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            @forelse ($posts as $post)
                <x-public.post-card :post="$post" :network="$network" />
            @empty
                <p class="text-sm text-slate-500">Todavía no hay publicaciones. Las novedades aparecerán aquí cuando la red las publique.</p>
            @endforelse
        </div>
    </section>

    <section>
        <div class="mb-4 flex items-end justify-between gap-4">
            <h2 class="text-xl font-semibold">Ayuda</h2>
            <a href="{{ route('public.help', $network->slug) }}" class="text-sm text-teal-800 hover:underline">Ver ayuda</a>
        </div>
        <ul class="space-y-2">
            @forelse ($pages as $page)
                <li>
                    <a href="{{ route('public.page', [$network->slug, $page->slug]) }}" class="hover:underline">{{ $page->title }}</a>
                </li>
            @empty
                <li class="text-sm text-slate-500">Todavía no hay páginas de ayuda. Cuando la red las publique, aparecerán aquí.</li>
            @endforelse
        </ul>
    </section>
@endsection
