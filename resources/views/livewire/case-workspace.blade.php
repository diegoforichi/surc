<div class="space-y-6">
    @if (session('message'))
        <div class="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-800">
            {{ session('message') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-2xl font-bold">{{ terminology('case', 'Caso') }}: {{ $case->title }}</h1>
            <p class="text-sm text-gray-600">
                Código: {{ $case->code ?? '—' }} |
                {{ terminology('organization', 'Sede') }}: {{ $case->organization?->name }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('cases.ticket', $case) }}" target="_blank"
                class="inline-flex items-center px-3 py-2 bg-gray-800 text-white text-sm rounded hover:bg-gray-700">
                Constancia 80mm
            </a>
            <a href="{{ route('cases.report', $case) }}" target="_blank"
                class="inline-flex items-center px-3 py-2 bg-slate-700 text-white text-sm rounded hover:bg-slate-600">
                Descargar informe
            </a>
        </div>
    </div>

    @if ($case->agenda)
        <div class="bg-sky-50 border border-sky-200 rounded-lg p-4">
            <p class="font-medium">{{ terminology('agenda', 'Agenda') }}: {{ $case->agenda->title ?: 'Visita programada' }}</p>
            <p class="text-sm text-gray-600">
                Fecha: {{ $case->agenda->scheduled_date?->format('d/m/Y') ?? '—' }}
                @if ($case->agenda->start_time)
                    · Inicio: {{ \Illuminate\Support\Str::of($case->agenda->start_time)->substr(0, 5) }}
                @endif
                @if ($case->agenda_order)
                    · Orden: {{ $case->agenda_order }}
                @endif
                @if ($case->agenda->specialist)
                    · {{ terminology('specialist', 'Especialista') }}: {{ $case->agenda->specialist->display_name }}
                @endif
            </p>
        </div>
    @elseif ($case->scheduled_at)
        <div class="bg-sky-50 border border-sky-200 rounded-lg p-4">
            <p class="text-sm text-gray-600">
                Hora estimada: {{ $case->scheduled_at->format('d/m/Y H:i') }}
            </p>
        </div>
    @endif

    @if ($case->status->value === 'closed' || $case->status->value === 'cancelled')
        <div class="rounded-lg border p-4 @if($case->status->value === 'closed') bg-green-50 border-green-200 @else bg-red-50 border-red-200 @endif">
            <p class="font-medium">{{ \App\Support\Cases\CaseStatusDisplay::label($case->status) }}</p>
            <p class="text-sm text-gray-600">
                {{ $case->closed_at?->format('d/m/Y H:i') ?? '—' }}
                @if ($case->closedByUser)
                    · por {{ $case->closedByUser->name }}
                @endif
            </p>
        </div>
    @endif

    @if ($this->orderedStages->isNotEmpty())
        <nav class="bg-white rounded-lg shadow p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Flujo del caso</p>
            <ol class="flex flex-col gap-2 md:flex-row md:items-stretch md:gap-0">
                @foreach ($this->orderedStages as $stage)
                    @php($state = $this->stageState($stage))
                    <li @class([
                        'flex-1 rounded-lg border px-3 py-2 text-sm md:rounded-none md:border-r-0 md:first:rounded-l-lg md:last:rounded-r-lg md:last:border-r',
                        'bg-green-50 border-green-300 text-green-900' => $state === 'done',
                        'bg-amber-50 border-amber-400 text-amber-900 ring-2 ring-amber-300' => $state === 'current',
                        'bg-gray-50 border-gray-200 text-gray-500' => in_array($state, ['pending', 'locked']),
                    ])>
                        <span class="block font-medium">{{ $stage->label }}</span>
                        <span class="text-xs">
                            @if ($state === 'done')
                                Completada
                            @elseif ($state === 'current')
                                En curso
                            @else
                                Pendiente
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
            @if ($this->canAdvanceStage())
                <button wire:click="advanceStage" type="button"
                    wire:loading.attr="disabled"
                    wire:target="advanceStage"
                    class="mt-4 px-4 py-2 bg-amber-600 text-white rounded text-sm hover:bg-amber-700">
                    <span wire:loading.remove wire:target="advanceStage">Avanzar etapa: {{ $case->currentStage->label }}</span>
                    <span wire:loading wire:target="advanceStage">Avanzando etapa...</span>
                </button>
            @endif
            @if ($this->canCancelCase())
                <button wire:click="cancelCase" type="button"
                    wire:confirm="Se cancelará el caso. ¿Confirmás?"
                    class="mt-4 ml-2 px-4 py-2 bg-red-700 text-white rounded text-sm hover:bg-red-800">
                    Cancelar caso
                </button>
            @endif
        </nav>
    @endif

    @php($paymentsState = $this->sectionState('payments'))
    @php($checklistState = $this->sectionState('checklist'))
    @php($summaryState = $this->sectionState('summary'))
    @php($consultationState = $this->sectionState('consultation'))

    <div class="grid md:grid-cols-2 gap-6">
        <section @class([
            'rounded-lg shadow p-4 space-y-3',
            'bg-white' => in_array($paymentsState, ['current', 'done']),
            'bg-gray-50 opacity-75' => $paymentsState === 'locked',
        ])>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Pagos / Seña</h2>
                @if ($paymentsState === 'done')
                    <span class="text-xs text-green-700">Completado</span>
                @elseif ($paymentsState === 'locked')
                    <span class="text-xs text-gray-500">Bloqueado</span>
                @endif
            </div>

            @if ($paymentsState === 'locked')
                <p class="text-sm text-gray-500">Disponible cuando el caso llegue a esta etapa.</p>
            @else
                @if ($this->canEditSection('payments'))
                    <div class="flex gap-2">
                        <input type="number" step="0.01" wire:model="depositAmount" placeholder="Monto"
                            class="rounded border-gray-300 text-sm flex-1">
                        <input type="text" wire:model="depositMethod" placeholder="Método"
                            class="rounded border-gray-300 text-sm flex-1">
                        <button wire:click="registerDeposit" type="button"
                            wire:loading.attr="disabled"
                            wire:target="registerDeposit"
                            class="px-3 py-2 bg-blue-600 text-white rounded text-sm disabled:bg-gray-400">
                            <span wire:loading.remove wire:target="registerDeposit">Registrar</span>
                            <span wire:loading wire:target="registerDeposit">Registrando...</span>
                        </button>
                    </div>
                @endif
                <ul class="text-sm space-y-1">
                    @forelse ($case->payments as $payment)
                        <li class="flex justify-between items-center border-b py-1">
                            <span>${{ number_format($payment->amount, 2) }} — {{ \App\Support\Labels\OperationalStatusLabels::payment($payment->status) }}</span>
                            @if ($payment->status === 'pending' && $this->canEditSection('payments') && $this->canConfirmPayments())
                                <button wire:click="confirmPayment({{ $payment->id }})" type="button"
                                    class="text-green-700 text-xs underline">Confirmar</button>
                            @endif
                        </li>
                    @empty
                        <li class="text-gray-500">Sin pagos registrados.</li>
                    @endforelse
                </ul>
            @endif
        </section>

        <section @class([
            'rounded-lg shadow p-4 space-y-3',
            'bg-white' => in_array($checklistState, ['current', 'done']),
            'bg-gray-50 opacity-75' => $checklistState === 'locked',
        ])>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Checklist de etapa</h2>
                @if ($checklistState === 'done')
                    <span class="text-xs text-green-700">Completado</span>
                @elseif ($checklistState === 'locked')
                    <span class="text-xs text-gray-500">Bloqueado</span>
                @endif
            </div>

            @if ($checklistState === 'locked')
                <p class="text-sm text-gray-500">Disponible cuando el caso llegue a esta etapa.</p>
            @else
                @forelse (\App\Support\Cases\CaseWorkspaceStages::sectionRequirements($case, 'checklist') as $requirement)
                    @if ($requirement->type->value === 'checkbox')
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox"
                                wire:click="toggleRequirement({{ $requirement->id }})"
                                @checked($requirementChecks[$requirement->id] ?? false)
                                @disabled(! $this->canEditSection('checklist'))>
                            {{ $requirement->label }}
                        </label>
                    @elseif ($requirement->type->value === 'file')
                        @php($isAttached = ($requirementChecks[$requirement->id] ?? false) || ($requirement->key === 'prior_studies' && $this->attachments->isNotEmpty()))
                        <p class="text-sm text-gray-600">
                            • {{ $requirement->label }} ({{ $requirement->type->label() }})
                            @if ($isAttached)
                                <span class="ml-2 text-xs text-green-700 font-medium">Adjuntado</span>
                            @endif
                        </p>
                    @else
                        <p class="text-sm text-gray-600">• {{ $requirement->label }} ({{ $requirement->type->label() }})</p>
                    @endif
                @empty
                    <p class="text-sm text-gray-500">Sin requisitos para esta etapa.</p>
                @endforelse
            @endif
        </section>
    </div>

    <section class="rounded-lg shadow p-4 space-y-3 bg-white">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">Archivos adjuntos</h2>
            @if (! $this->canManageAttachments())
                <span class="text-xs text-gray-500">Solo lectura</span>
            @endif
        </div>

        <ul class="space-y-2 text-sm">
            @forelse ($this->attachments as $media)
                <li class="flex items-center justify-between gap-2 border-b pb-2">
                    <div>
                        <a href="{{ route('cases.attachments.show', [$case, $media]) }}" target="_blank" class="text-blue-700 hover:underline">
                            {{ $media->file_name }}
                        </a>
                        <p class="text-xs text-gray-500">
                            {{ number_format(((int) $media->size) / 1024, 1) }} KB
                        </p>
                    </div>
                    @if ($this->canManageAttachments())
                        <button wire:click="deleteAttachment({{ $media->id }})"
                            wire:confirm="¿Seguro que querés eliminar este archivo?"
                            type="button"
                            class="px-3 py-1 text-xs rounded bg-red-100 text-red-700 hover:bg-red-200">
                            Eliminar
                        </button>
                    @endif
                </li>
            @empty
                <li class="text-gray-500">Sin archivos adjuntos.</li>
            @endforelse
        </ul>

        @if ($this->canManageAttachments())
            <div class="pt-2 border-t">
                <input type="file" wire:model="attachment" class="text-sm">
                <button wire:click="uploadAttachment" type="button"
                    wire:loading.attr="disabled"
                    wire:target="uploadAttachment"
                    class="mt-2 px-3 py-1 bg-gray-700 text-white rounded text-sm disabled:bg-gray-400">
                    <span wire:loading.remove wire:target="uploadAttachment">Adjuntar archivo</span>
                    <span wire:loading wire:target="uploadAttachment">Adjuntando...</span>
                </button>
            </div>
        @endif
    </section>

    <section class="rounded-lg shadow p-4 space-y-3 bg-white">
        <h2 class="font-semibold">Constancia firmada</h2>
        @forelse ($this->signedConstancies as $media)
            <p class="text-sm">
                <a href="{{ route('cases.attachments.show', [$case, $media]) }}" class="text-blue-700 underline" target="_blank">{{ $media->file_name }}</a>
            </p>
        @empty
            <p class="text-sm text-gray-500">Aún no se adjuntó la constancia firmada en papel.</p>
        @endforelse
        @if ($this->canManageAttachments())
            <input type="file" wire:model="signedConstancy" class="text-sm">
            <button wire:click="uploadSignedConstancy" type="button" class="px-3 py-1 bg-gray-700 text-white rounded text-sm">
                Adjuntar constancia firmada
            </button>
        @endif
    </section>

    @if ($this->sharedHistoryEntries->isNotEmpty() || $this->canIncorporateHistory)
        <section class="rounded-lg shadow p-4 space-y-3 bg-white">
            <h2 class="font-semibold">{{ terminology('history', 'Historial') }} compartido</h2>
            <ul class="text-sm space-y-2">
                @forelse ($this->sharedHistoryEntries as $entry)
                    <li>
                        <span class="font-medium">{{ $entry->type?->label }}</span>
                        — {{ $entry->summary }}
                        <span class="text-gray-500">({{ $entry->occurred_at?->format('d/m/Y') }})</span>
                    </li>
                @empty
                    <li class="text-gray-500">No hay registros compartidos con esta derivación.</li>
                @endforelse
            </ul>
            @if ($this->canIncorporateHistory)
                <button wire:click="incorporateIntoHistory" type="button"
                    wire:confirm="Se creará un registro final en el historial de la sede de origen. ¿Confirmás?"
                    class="px-3 py-2 bg-teal-700 text-white rounded text-sm">
                    Incorporar resultado al historial
                </button>
            @endif
        </section>
    @endif

    <section @class([
        'rounded-lg shadow p-4 space-y-3',
        'bg-white' => in_array($summaryState, ['current', 'done']),
        'bg-gray-50 opacity-75' => $summaryState === 'locked',
    ])>
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">Ficha resumida</h2>
            @if ($summaryState === 'done')
                <span class="text-xs text-green-700">Completado</span>
            @elseif ($summaryState === 'locked')
                <span class="text-xs text-gray-500">Bloqueado</span>
            @endif
        </div>

        @if ($summaryState === 'locked')
            <p class="text-sm text-gray-500">Disponible cuando el caso llegue a esta etapa.</p>
        @else
            <textarea wire:model="summary" rows="5" class="w-full rounded border-gray-300"
                @disabled(! $this->canEditSection('summary'))></textarea>
            @if ($this->canEditSection('summary'))
                <livewire:custom-fields-form wire:model="metadata" :entity-type="'case'"
                    :key="'case-fields-'.$case->id" />
                <button wire:click="saveSummary" type="button"
                    wire:target="summary,metadata"
                    wire:dirty.class="bg-teal-600 text-white"
                    wire:dirty.class.remove="bg-gray-300 text-gray-600 cursor-not-allowed"
                    wire:loading.attr="disabled"
                    wire:target="saveSummary"
                    class="px-4 py-2 rounded text-sm bg-gray-300 text-gray-600 cursor-not-allowed">
                    <span wire:loading.remove wire:target="saveSummary">
                        <span wire:dirty.remove wire:target="summary,metadata">Guardado</span>
                        <span wire:dirty wire:target="summary,metadata">Guardar ficha</span>
                    </span>
                    <span wire:loading wire:target="saveSummary">Guardando...</span>
                </button>
            @else
                <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $summary ?: 'Sin resumen cargado.' }}</p>
            @endif
        @endif
    </section>

    <section @class([
        'rounded-lg shadow p-4 space-y-3',
        'bg-white' => in_array($consultationState, ['current', 'done']),
        'bg-gray-50 opacity-75' => $consultationState === 'locked',
    ])>
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">{{ terminology('ux.consultation_title', 'Cierre') }}</h2>
            @if ($consultationState === 'done')
                <span class="text-xs text-green-700">Completado</span>
            @elseif ($consultationState === 'locked')
                <span class="text-xs text-gray-500">Bloqueado</span>
            @endif
        </div>

        @if ($consultationState === 'locked')
            <p class="text-sm text-gray-500">Disponible cuando el caso llegue a esta etapa.</p>
        @elseif ($this->canFinalizeConsultation())
            <p class="text-xs font-medium text-gray-600">{{ terminology('ux.case_diagnosis', 'Hallazgos') }}</p>
            <textarea wire:model="diagnosis" rows="3" placeholder="{{ terminology('ux.case_diagnosis', 'Hallazgos') }}"
                class="w-full rounded border-gray-300"></textarea>
            <p class="text-xs font-medium text-gray-600">{{ terminology('ux.case_treatment', 'Trabajo a realizar') }}</p>
            <textarea wire:model="treatment" rows="3" placeholder="{{ terminology('ux.case_treatment', 'Trabajo a realizar') }}"
                class="w-full rounded border-gray-300"></textarea>
            <textarea wire:model="consultationNotes" rows="2" placeholder="Notas de consulta (opcional)"
                class="w-full rounded border-gray-300"></textarea>
            <p class="text-xs font-medium text-gray-600">Responsable interviniente</p>
            <select wire:model="technicalResponsiblePartyId" class="w-full rounded border-gray-300 text-sm">
                <option value="">Seleccionar {{ \Illuminate\Support\Str::lower(terminology('specialist', 'especialista')) }} (opcional)</option>
                @foreach ($this->specialists as $specialist)
                    <option value="{{ $specialist->id }}">{{ $specialist->display_name }}</option>
                @endforeach
            </select>
            <input type="text" wire:model="technicalResponsibleName"
                placeholder="O nombre del técnico sin usuario"
                class="w-full rounded border-gray-300 text-sm">
            <button wire:click="finishConsultation" type="button"
                wire:confirm="El caso quedará cerrado y no se podrá volver a editar. ¿Confirmás?"
                wire:loading.attr="disabled"
                wire:target="finishConsultation"
                class="px-4 py-2 bg-indigo-600 text-white rounded text-sm disabled:bg-gray-400">
                <span wire:loading.remove wire:target="finishConsultation">{{ terminology('ux.action_finish', 'Finalizar y cerrar') }}</span>
                <span wire:loading wire:target="finishConsultation">Finalizando...</span>
            </button>
        @else
            @php($lastConsultation = $case->events->firstWhere('type', 'consultation'))
            <p class="text-sm text-gray-600 whitespace-pre-wrap">
                {{ $metadata['diagnosis'] ?? $lastConsultation?->description ?? 'Sin consulta registrada.' }}
            </p>
        @endif
    </section>

    <section class="bg-white rounded-lg shadow p-4">
        <h2 class="font-semibold mb-3">Auditoría ({{ $this->auditEntries->count() }})</h2>
        <div class="max-h-80 overflow-y-auto pr-2">
            <ul class="space-y-2 text-sm">
                @forelse ($this->auditEntries as $entry)
                    <li class="border-l-2 border-gray-300 pl-3">
                        <span class="font-medium">{{ $entry['label'] }}</span>
                        @if ($entry['description'])
                            — {{ $entry['description'] }}
                        @endif
                        <span class="text-gray-500">
                            ({{ $entry['author'] }} · {{ $entry['at']?->format('d/m/Y H:i') }})
                        </span>
                    </li>
                @empty
                    <li class="text-gray-500">Sin actividad registrada aún.</li>
                @endforelse
            </ul>
        </div>
    </section>
</div>
