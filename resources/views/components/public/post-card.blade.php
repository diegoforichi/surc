@props(['post', 'network'])

<a href="{{ route('public.post', [$network->slug, $post->slug]) }}" class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-teal-600 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-teal-600">
    <x-public.media :path="$post->image_path" :alt="$post->title" class="h-36 w-full object-cover" />
    <div class="space-y-2 p-4">
        <h3 class="font-semibold text-slate-900 group-hover:text-teal-800">{{ $post->title }}</h3>
        @if ($post->excerpt)
            <p class="text-sm text-slate-600">{{ $post->excerpt }}</p>
        @endif
        @if ($post->published_at)
            <p class="text-xs text-slate-400">{{ $post->published_at->format('d/m/Y') }}</p>
        @endif
    </div>
</a>
