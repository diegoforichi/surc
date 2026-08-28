@extends('public.layout', ['seoDescription' => $page->seo_description])

@section('title', $page->title.' — '.$network->name)

@section('content')
    <a href="{{ route('public.home', $network->slug) }}" class="text-sm text-teal-800">&larr; Volver</a>
    <h1 class="text-3xl font-bold mt-4">{{ $page->title }}</h1>
    <div class="prose mt-6">{!! \App\Support\Html\SafeHtml::render($page->body) !!}</div>
@endsection
