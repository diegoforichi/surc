@extends('public.layout')

@section('title', terminology('organization', 'Sedes').' — '.$network->name)

@section('content')
    <h1 class="mb-6 text-3xl font-bold">{{ terminology('organization', 'Sedes') }}</h1>
    <div class="grid gap-4 md:grid-cols-3">
        @forelse ($organizations as $organization)
            <x-public.organization-card :organization="$organization" :network="$network" />
        @empty
            <p class="text-sm text-slate-500">Todavía no hay sedes publicadas.</p>
        @endforelse
    </div>
@endsection
