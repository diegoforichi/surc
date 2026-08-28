@php
    $pairs = \App\Support\History\HistoryFieldSchema::displayPairs($entry->type?->field_schema, $entry->payload);
    $attachments = $entry->getMedia('attachments');
    $sharedCases = $entry->shares
        ->map(fn ($share) => $share->case?->code ?: $share->case?->title)
        ->filter()
        ->unique()
        ->values();
@endphp

<section class="{{ $isAddendum ? 'addendum' : 'entry' }}">
    <h2>
        @if ($isAddendum)
            Adenda
        @else
            {{ $entry->type?->label ?? terminology('history_entry', 'Registro') }}
        @endif
    </h2>
    <table class="grid">
        <tr>
            <th>Fecha</th>
            <td>{{ $entry->occurred_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <th>Resumen</th>
            <td>{{ $entry->summary ?: '—' }}</td>
        </tr>
        <tr>
            <th>Autor</th>
            <td>{{ $entry->author?->name ?? '—' }}</td>
        </tr>
        <tr>
            <th>Finalizado</th>
            <td>
                {{ $entry->finalizer?->name ?? $entry->author?->name ?? '—' }}
                @if ($entry->finalized_at)
                    — {{ $entry->finalized_at->format('d/m/Y H:i') }}
                @endif
            </td>
        </tr>
        @if ($sharedCases->isNotEmpty())
            <tr>
                <th>{{ terminology('case', 'Casos') }} compartidos</th>
                <td>{{ $sharedCases->implode(', ') }}</td>
            </tr>
        @endif
    </table>

    @if ($pairs !== [])
        <h3>Detalle</h3>
        <table class="grid">
            @foreach ($pairs as $pair)
                <tr>
                    <th>{{ $pair['label'] }}</th>
                    <td>{{ $pair['value'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p><strong>Adjuntos:</strong>
        @if ($attachments->isEmpty())
            ninguno
        @else
            {{ $attachments->map(fn ($media) => $media->file_name)->implode(', ') }}
        @endif
    </p>

    @if (! $isAddendum && $entry->addenda->isNotEmpty())
        @foreach ($entry->addenda as $addendum)
            @include('history.entry-block', ['entry' => $addendum, 'isAddendum' => true])
        @endforeach
    @endif
</section>
