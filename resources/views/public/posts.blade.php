@extends('public.layout')

@section('title', 'Blog — '.$network->name)

@section('content')
    <h1 class="mb-6 text-3xl font-bold">Blog</h1>
    <div class="grid gap-4 md:grid-cols-3">
        @forelse ($posts as $post)
            <x-public.post-card :post="$post" :network="$network" />
        @empty
            <p class="text-sm text-slate-500">Todavía no hay publicaciones. Las novedades aparecerán aquí cuando la red las publique.</p>
        @endforelse
    </div>
@endsection
