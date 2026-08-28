@extends('public.layout')

@section('title', terminology('specialist', 'Especialistas').' — '.$network->name)

@section('content')
    <h1 class="mb-6 text-3xl font-bold">{{ terminology('specialist', 'Especialistas') }}</h1>
    <div class="grid gap-4 md:grid-cols-3">
        @forelse ($specialists as $specialist)
            <x-public.specialist-card :specialist="$specialist" :network="$network" />
        @empty
            <p class="text-sm text-slate-500">Todavía no hay perfiles publicados. Esta sección se actualizará cuando la red cargue especialistas en el directorio.</p>
        @endforelse
    </div>
@endsection
