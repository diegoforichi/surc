@extends('public.layout')

@section('title', $party->display_name.' — '.$network->name)

@section('content')
    <a href="{{ route('public.specialists', $network->slug) }}" class="text-sm text-teal-800 hover:underline">&larr; Volver a {{ terminology('specialist', 'especialistas') }}</a>
    <div class="mt-4 grid gap-6 {{ $party->photo_path ? 'md:grid-cols-3' : '' }}">
        @if ($party->photo_path)
            <div>
                <x-public.media :path="$party->photo_path" :alt="$party->display_name" class="h-64 w-full rounded-xl object-cover" />
            </div>
        @endif
        <div class="{{ $party->photo_path ? 'md:col-span-2' : '' }}">
            <h1 class="text-3xl font-bold">{{ $party->display_name }}</h1>
            <p class="mt-1 text-slate-600">{{ $party->actorType?->label }} · {{ $party->organization?->name }}</p>
            <div class="prose mt-4">{!! \App\Support\Html\SafeHtml::render($party->bio) !!}</div>
            <div class="mt-4 space-y-1 text-sm">
                @if ($party->organization)
                    <p><a class="underline" href="{{ route('public.organization', [$network->slug, $party->organization->slug]) }}">{{ $party->organization->name }}</a></p>
                @endif
                @if ($party->whatsappUrl())
                    <p><a class="mt-2 inline-block rounded-lg bg-teal-700 px-4 py-2 text-white" href="{{ $party->whatsappUrl() }}" target="_blank" rel="noopener">WhatsApp</a></p>
                @endif
            </div>
        </div>
    </div>
@endsection
