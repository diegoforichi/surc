@if ($entries->isEmpty())
    <p class="muted">No hay registros finales para imprimir.</p>
@else
    @foreach ($entries as $entry)
        @include('history.entry-block', ['entry' => $entry, 'isAddendum' => false])
    @endforeach
@endif
