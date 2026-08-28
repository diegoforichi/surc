@extends('public.layout', ['seoDescription' => $post->seo_description])

@section('title', $post->title.' — '.$network->name)

@section('content')
    <a href="{{ route('public.posts', $network->slug) }}" class="text-sm text-teal-800 hover:underline">&larr; Volver al blog</a>
    <h1 class="mt-4 text-3xl font-bold">{{ $post->title }}</h1>
    <p class="text-sm text-slate-500">{{ $post->published_at?->format('d/m/Y') }}</p>
    @if ($post->image_path)
        <x-public.media :path="$post->image_path" :alt="$post->title" class="mt-4 h-72 w-full rounded-xl object-cover" />
    @endif
    <div class="prose mt-6">{!! \App\Support\Html\SafeHtml::render($post->body) !!}</div>
@endsection
