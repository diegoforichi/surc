@extends('public.layout')

@section('title', 'Ayuda — '.$network->name)

@section('content')
    <h1 class="text-3xl font-bold mb-6">Ayuda</h1>
    <div class="grid md:grid-cols-4 gap-8">
        <aside>
            <ul class="space-y-2 text-sm">
                @forelse ($pages as $page)
                    <li>
                        <a href="{{ route('public.page', [$network->slug, $page->slug]) }}" class="hover:underline">{{ $page->title }}</a>
                    </li>
                @empty
                    <li class="text-gray-500">Aún no hay páginas de ayuda.</li>
                @endforelse
            </ul>
        </aside>
        <article class="md:col-span-3">
            @if ($help)
                <h2 class="text-2xl font-semibold">{{ $help->title }}</h2>
                <div class="prose mt-4">{!! \App\Support\Html\SafeHtml::render($help->body) !!}</div>
            @else
                <p class="text-gray-600">Cuando la red publique páginas de ayuda, aparecerán aquí.</p>
            @endif
        </article>
    </div>
@endsection
