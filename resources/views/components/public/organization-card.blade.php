@props(['organization', 'network'])

<a href="{{ route('public.organization', [$network->slug, $organization->slug]) }}" class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-teal-600 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-teal-600">
    <x-public.media :path="$organization->photo_path" :alt="$organization->name" />
    <div class="space-y-1 p-4">
        <h3 class="font-semibold text-slate-900 group-hover:text-teal-800">{{ $organization->name }}</h3>
        @if ($organization->address)
            <p class="text-sm text-slate-600">{{ $organization->address }}</p>
        @endif
        @if ($organization->phone)
            <p class="text-sm text-slate-500">{{ $organization->phone }}</p>
        @endif
    </div>
</a>
