<x-filament-panels::page>
    @php($stats = $this->headerStats())

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <div>
                <div class="text-gray-500">{{ terminology('subject', 'Sujeto') }}</div>
                <div class="font-semibold">{{ $this->getRecord()->label_name }}</div>
            </div>
            <div>
                <div class="text-gray-500">{{ terminology('client', 'Titular') }}</div>
                <div class="font-semibold">{{ $stats['owner'] ?: '—' }}</div>
            </div>
            <div>
                <div class="text-gray-500">{{ terminology('organization', 'Sede') }}</div>
                <div class="font-semibold">{{ $stats['organization'] ?: '—' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Último peso</div>
                <div class="font-semibold">{{ $stats['weight'] ?: '—' }}</div>
            </div>
        </div>
        @if ($stats['upcoming'] !== [])
            <div class="mt-3 text-sm">
                <span class="text-gray-500">Próximos:</span>
                @foreach ($stats['upcoming'] as $event)
                    <span class="ml-2">{{ $event['date']?->format('d/m/Y') }} {{ $event['label'] }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mt-4">
        @livewire(\App\Livewire\SubjectHistoryTimeline::class, ['subject' => $this->getRecord()], key('history-'.$this->getRecord()->getKey()))
    </div>
</x-filament-panels::page>
