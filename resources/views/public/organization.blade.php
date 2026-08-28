@extends('public.layout')

@section('title', $organization->name.' — '.$network->name)

@section('content')
    <a href="{{ route('public.organizations', $network->slug) }}" class="text-sm text-teal-800 hover:underline">&larr; Volver a {{ terminology('organization', 'sedes') }}</a>
    <div class="mt-4 grid gap-6 md:grid-cols-2">
        <div>
            <x-public.media :path="$organization->photo_path" :alt="$organization->name" class="mb-4 h-64 w-full rounded-xl object-cover" />
            <h1 class="text-3xl font-bold">{{ $organization->name }}</h1>
            <p class="mt-2 text-slate-600">{{ $organization->address }}</p>
            <p class="mt-2">{{ $organization->phone }}</p>
            @if ($organization->email)
                <p><a class="text-teal-800 underline" href="mailto:{{ $organization->email }}">{{ $organization->email }}</a></p>
            @endif
            @if ($organization->website)
                <p><a class="text-teal-800 underline" href="{{ $organization->website }}" target="_blank" rel="noopener">{{ $organization->website }}</a></p>
            @endif
            @if ($organization->whatsappUrl())
                <p class="mt-3">
                    <a class="inline-block rounded-lg bg-teal-700 px-4 py-2 text-white" href="{{ $organization->whatsappUrl() }}" target="_blank" rel="noopener">WhatsApp</a>
                </p>
            @endif
        </div>
        <div>
            <div class="prose">{!! \App\Support\Html\SafeHtml::render($organization->description) !!}</div>
            <h2 class="mt-6 text-lg font-semibold">{{ terminology('specialist', 'Especialistas') }}</h2>
            <ul class="mt-2 space-y-2">
                @forelse ($specialists as $specialist)
                    <li>
                        <a class="hover:underline" href="{{ route('public.specialist', [$network->slug, $specialist]) }}">{{ $specialist->display_name }}</a>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">Sin perfiles publicados en esta sede.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
