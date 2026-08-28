@props(['specialist', 'network'])

<a href="{{ route('public.specialist', [$network->slug, $specialist]) }}" class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-teal-600 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-teal-600">
    <x-public.media :path="$specialist->photo_path" :alt="$specialist->display_name" />
    <div class="space-y-1 p-4">
        <h3 class="font-semibold text-slate-900 group-hover:text-teal-800">{{ $specialist->display_name }}</h3>
        <p class="text-sm text-slate-600">{{ $specialist->organization?->name }}</p>
    </div>
</a>
