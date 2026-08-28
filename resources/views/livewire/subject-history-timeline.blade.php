<div>
    <div class="mb-4 flex flex-wrap items-center gap-2">
        @if ($this->canManage())
            {{ $this->createAction }}
            @foreach ($this->quickTypes() as $type)
                <button type="button" wire:click="startType({{ $type->id }})"
                    class="fi-btn fi-btn-size-sm fi-btn-color-gray rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200">
                    + {{ $type->label }}
                </button>
            @endforeach
        @endif
    </div>

    <div class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar..."
            class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
        <select wire:model.live="typeFilter" class="fi-select block rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
            <option value="">Todos los tipos</option>
            @foreach ($this->quickTypes() as $type)
                <option value="{{ $type->id }}">{{ $type->label }}</option>
            @endforeach
        </select>
        <select wire:model.live="statusFilter" class="fi-select block rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
            <option value="">Todos los estados</option>
            <option value="draft">Borrador</option>
            <option value="final">Final</option>
        </select>
        <div class="flex gap-2">
            <input type="date" wire:model.live="from" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
            <input type="date" wire:model.live="until" class="fi-input w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($this->entries() as $entry)
            @php
                $pairs = \App\Support\History\HistoryFieldSchema::displayPairs($entry->type?->field_schema, $entry->payload);
                $attachments = $entry->getMedia('attachments');
            @endphp
            <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="text-sm text-gray-500">{{ $entry->occurred_at?->format('d/m/Y H:i') }}</div>
                        <h3 class="text-base font-semibold">{{ $entry->type?->label ?? terminology('history_entry', 'Registro') }}</h3>
                    </div>
                    <span @class([
                        'rounded-full px-2 py-1 text-xs font-medium',
                        'bg-amber-100 text-amber-800' => ! $entry->isFinal(),
                        'bg-emerald-100 text-emerald-800' => $entry->isFinal(),
                    ])>
                        {{ $entry->isFinal() ? 'Final' : 'Borrador' }}
                    </span>
                </div>

                <p class="mt-2 text-sm">
                    {{ $entry->summary ?: ($this->proposedSummary($entry) ?: 'Sin resumen') }}
                </p>
                <p class="mt-1 text-xs text-gray-500">{{ $entry->author?->name }}</p>

                @if ($pairs !== [])
                    <dl class="mt-3 grid gap-1 text-sm sm:grid-cols-2">
                        @foreach ($pairs as $pair)
                            @if ($pair['value'] !== '—')
                                <div>
                                    <dt class="text-gray-500">{{ $pair['label'] }}</dt>
                                    <dd>{{ $pair['value'] }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                @endif

                @if ($entry->addenda->isNotEmpty())
                    <div class="mt-3 border-l-2 border-gray-200 pl-3 text-sm">
                        @foreach ($entry->addenda as $addendum)
                            <p><strong>Adenda</strong> {{ $addendum->occurred_at?->format('d/m/Y') }} — {{ $addendum->summary }}</p>
                        @endforeach
                    </div>
                @endif

                @if ($attachments->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($attachments as $media)
                            @php($url = route('history.attachments.show', [$entry, $media]))
                            @if (str_starts_with((string) $media->mime_type, 'image/'))
                                <a href="{{ $url }}" target="_blank" rel="noopener">
                                    <img src="{{ $url }}" alt="{{ $media->file_name }}" class="h-16 w-16 rounded object-cover">
                                </a>
                            @else
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="text-sm text-primary-600 underline">{{ $media->file_name }}</a>
                            @endif
                        @endforeach
                    </div>
                @endif

                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    @if ($this->canManage() && ! $entry->isFinal())
                        {{ ($this->editAction)(['entry' => $entry->id]) }}
                        {{ ($this->finalizeAction)(['entry' => $entry->id]) }}
                    @endif
                    @if ($this->canManage() && $entry->isFinal())
                        {{ ($this->addendumAction)(['entry' => $entry->id]) }}
                    @endif
                    @if ($entry->isFinal() && auth()->user()?->can('history.share'))
                        {{ ($this->shareAction)(['entry' => $entry->id]) }}
                    @endif
                    @if (\App\Support\History\HistoryAccess::canPrintEntry(auth()->user(), $entry))
                        <a href="{{ route('history.entries.pdf', $entry) }}" target="_blank" class="fi-link text-primary-600">PDF</a>
                    @endif
                    @if ($this->canSell($entry))
                        {{ ($this->salesOrderAction)(['entry' => $entry->id]) }}
                    @endif
                </div>
            </article>
        @empty
            <p class="text-sm text-gray-500">Aún no hay registros en esta ficha.</p>
        @endforelse
    </div>

    <x-filament-actions::modals />
</div>
