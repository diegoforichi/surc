@props(['url'])

@php
    $src = \App\Support\Html\VideoEmbed::embedSrc($url);
@endphp

@if ($src)
    <div class="aspect-video w-full overflow-hidden rounded-lg bg-slate-900">
        <iframe
            src="{{ $src }}"
            title="Video de capacitación"
            class="h-full w-full"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            referrerpolicy="strict-origin-when-cross-origin"
            allowfullscreen
        ></iframe>
    </div>
@endif
