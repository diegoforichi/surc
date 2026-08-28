<?php

namespace App\Livewire;

use App\Actions\Cases\AdvanceCaseStage;
use App\Actions\Cases\InitializeCaseWorkflow;
use App\Actions\Agendas\RecalculateAgendaStatus;
use App\Enums\CaseStatus;
use App\Models\CaseEvent;
use App\Models\CaseRecord;
use App\Models\CaseRequirementCompletion;
use App\Models\Party;
use App\Models\Payment;
use App\Models\StageRequirement;
use App\Models\WorkflowStage;
use App\Support\Cases\CaseEventLabels;
use App\Support\Cases\CaseOperationalAccess;
use App\Support\Cases\CaseWorkspaceStages;
use App\Support\Settings\NetworkSettings;
use App\Actions\History\IncorporateCaseIntoHistory;
use App\Support\History\HistoryAccess;
use App\Support\Media\AllowedUploads;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CaseWorkspace extends Component
{
    use WithFileUploads;

    public CaseRecord $case;

    public string $summary = '';

    public array $metadata = [];

    public ?int $technicalResponsiblePartyId = null;

    public string $technicalResponsibleName = '';

    public string $consultationNotes = '';

    public string $diagnosis = '';

    public string $treatment = '';

    public float $depositAmount = 0;

    public string $depositMethod = '';

    public array $requirementChecks = [];

    public $attachment;

    public $signedConstancy;

    public function mount(CaseRecord $case): void
    {
        if (! CaseOperationalAccess::canAccessCase($case)) {
            abort(403, 'No tiene permiso para acceder a este caso.');
        }

        $this->case = $case->load([
            'organization',
            'subject.owner',
            'agenda.specialist',
            'closedByUser',
            'currentStage.requirements',
            'stageStatuses.stage',
            'requirementCompletions.requirement',
            'events.author',
            'events.technicalResponsible',
            'payments',
        ]);

        if ($this->case->current_stage_id === null) {
            $this->case = app(InitializeCaseWorkflow::class)->handle($this->case);
            $this->case->load(['currentStage.requirements', 'stageStatuses.stage']);
        }

        $this->summary = $this->case->summary ?? '';
        $this->metadata = $this->case->metadata ?? [];
        $this->diagnosis = (string) ($this->metadata['diagnosis'] ?? '');
        $this->treatment = (string) ($this->metadata['treatment'] ?? '');

        foreach ($this->case->requirementCompletions as $completion) {
            $this->requirementChecks[$completion->stage_requirement_id] = $completion->is_completed;
        }
    }

    public function saveSummary(): void
    {
        CaseWorkspaceStages::assertCanEditSection($this->case, 'summary');

        $this->case->update([
            'summary' => $this->summary,
            'metadata' => $this->metadata,
        ]);

        CaseEvent::create([
            'case_id' => $this->case->id,
            'type' => 'summary_updated',
            'description' => 'Ficha resumida actualizada',
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);

        $this->case->load('events.author');
        session()->flash('message', 'Ficha guardada correctamente.');
    }

    public function registerDeposit(): void
    {
        CaseWorkspaceStages::assertCanEditSection($this->case, 'payments');

        $this->validate([
            'depositAmount' => 'required|numeric|min:0.01',
            'depositMethod' => 'required|string|max:100',
        ]);

        Payment::create([
            'network_id' => $this->case->network_id,
            'case_id' => $this->case->id,
            'type' => 'deposit',
            'amount' => $this->depositAmount,
            'status' => 'pending',
            'method' => $this->depositMethod,
        ]);

        $this->reset(['depositAmount', 'depositMethod']);
        $this->case->load('payments');
        session()->flash('message', 'Seña registrada.');
    }

    public function confirmPayment(int $paymentId): void
    {
        if (! Auth::user()?->can('payments.confirm')) {
            abort(403, 'No tiene permiso para confirmar pagos.');
        }

        CaseWorkspaceStages::assertCanEditSection($this->case, 'payments');

        $payment = Payment::query()
            ->where('case_id', $this->case->id)
            ->findOrFail($paymentId);

        $payment->update([
            'status' => 'confirmed',
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);

        $requirementId = $this->findRequirementId('payment_confirmed')
            ?? $this->findRequirementId('deposit_registered');

        if ($requirementId !== null) {
            CaseRequirementCompletion::updateOrCreate(
                [
                    'case_id' => $this->case->id,
                    'stage_requirement_id' => $requirementId,
                ],
                [
                    'is_completed' => true,
                    'completed_by' => Auth::id(),
                    'completed_at' => now(),
                ]
            );
        }

        $this->case->load('payments');
        session()->flash('message', 'Pago confirmado.');
    }

    public function toggleRequirement(int $requirementId): void
    {
        CaseWorkspaceStages::assertCanEditSection($this->case, 'checklist');

        $completed = ! ($this->requirementChecks[$requirementId] ?? false);
        $this->requirementChecks[$requirementId] = $completed;

        CaseRequirementCompletion::updateOrCreate(
            ['case_id' => $this->case->id, 'stage_requirement_id' => $requirementId],
            [
                'is_completed' => $completed,
                'completed_by' => $completed ? Auth::id() : null,
                'completed_at' => $completed ? now() : null,
            ]
        );
    }

    public function finishConsultation(): void
    {
        CaseWorkspaceStages::assertCanEditSection($this->case, 'consultation');

        $requiresDiagnosis = (bool) NetworkSettings::getForNetworkId(
            $this->case->network_id,
            'case.finish_requires_diagnosis',
            true,
        );
        $requiresTechnical = (bool) NetworkSettings::getForNetworkId(
            $this->case->network_id,
            'case.finish_requires_technical',
            true,
        );

        $this->validate(
            [
                'consultationNotes' => 'nullable|string|min:3',
                'diagnosis' => $requiresDiagnosis ? 'required|string|min:3' : 'nullable|string',
                'treatment' => 'nullable|string',
            ],
            [],
            [
                'diagnosis' => terminology('ux.case_diagnosis', 'Hallazgos'),
                'treatment' => terminology('ux.case_treatment', 'Trabajo a realizar'),
            ],
        );

        if ($requiresTechnical && ! $this->technicalResponsiblePartyId && blank($this->technicalResponsibleName)) {
            throw ValidationException::withMessages([
                'technicalResponsibleName' => 'Debe indicar el técnico interviniente.',
            ]);
        }

        $this->metadata['diagnosis'] = $this->diagnosis;
        $this->metadata['treatment'] = $this->treatment;

        $this->case->update([
            'metadata' => $this->metadata,
        ]);

        CaseEvent::create([
            'case_id' => $this->case->id,
            'type' => 'consultation',
            'description' => $this->consultationNotes ?: $this->diagnosis,
            'technical_responsible_party_id' => $this->technicalResponsiblePartyId,
            'technical_responsible_name' => $this->technicalResponsibleName ?: null,
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);

        $this->advanceStage();

        $this->case->load('events.author');
        session()->flash('message', 'Consulta finalizada.');
    }

    public function uploadAttachment(): void
    {
        if (! $this->canManageAttachments()) {
            throw ValidationException::withMessages([
                'attachment' => 'No se pueden modificar adjuntos cuando el caso está cerrado o cancelado.',
            ]);
        }

        $this->validate(['attachment' => 'required|'.AllowedUploads::validationRule()]);

        $this->case
            ->addMedia($this->attachment->getRealPath())
            ->usingFileName($this->attachment->getClientOriginalName())
            ->toMediaCollection('attachments');

        $reqId = $this->findRequirementId('prior_studies');
        if ($reqId) {
            $this->requirementChecks[$reqId] = true;
            CaseRequirementCompletion::updateOrCreate(
                ['case_id' => $this->case->id, 'stage_requirement_id' => $reqId],
                [
                    'is_completed' => true,
                    'completed_by' => Auth::id(),
                    'completed_at' => now(),
                ]
            );
        }

        $this->reset('attachment');
        session()->flash('message', 'Archivo adjuntado.');
    }

    public function deleteAttachment(int $mediaId): void
    {
        if (! $this->canManageAttachments()) {
            throw ValidationException::withMessages([
                'attachment' => 'No se pueden modificar adjuntos cuando el caso está cerrado o cancelado.',
            ]);
        }

        $media = Media::query()
            ->where('id', $mediaId)
            ->where('model_type', CaseRecord::class)
            ->where('model_id', $this->case->id)
            ->where('collection_name', 'attachments')
            ->firstOrFail();

        $media->delete();
        $this->case->unsetRelation('media');

        $isOnChecklistStage = CaseWorkspaceStages::sectionState($this->case, 'checklist') === 'current';
        if ($isOnChecklistStage && $this->attachments->isEmpty()) {
            $reqId = $this->findRequirementId('prior_studies');
            if ($reqId) {
                $this->requirementChecks[$reqId] = false;
                CaseRequirementCompletion::updateOrCreate(
                    ['case_id' => $this->case->id, 'stage_requirement_id' => $reqId],
                    [
                        'is_completed' => false,
                        'completed_by' => null,
                        'completed_at' => null,
                    ]
                );
            }
        }

        session()->flash('message', 'Archivo eliminado.');
    }

    public function uploadSignedConstancy(): void
    {
        if (! $this->canManageAttachments()) {
            throw ValidationException::withMessages([
                'signedConstancy' => 'No se pueden modificar adjuntos cuando el caso está cerrado o cancelado.',
            ]);
        }

        $this->validate(['signedConstancy' => 'required|'.AllowedUploads::validationRule()]);

        $this->case
            ->addMedia($this->signedConstancy->getRealPath())
            ->usingFileName($this->signedConstancy->getClientOriginalName())
            ->toMediaCollection('signed_constancy');

        $this->reset('signedConstancy');
        session()->flash('message', 'Constancia firmada adjuntada.');
    }

    public function incorporateIntoHistory(): void
    {
        try {
            $entry = app(IncorporateCaseIntoHistory::class)->handle($this->case, Auth::user());
        } catch (ValidationException $exception) {
            session()->flash('error', collect($exception->errors())->flatten()->first());

            return;
        }

        $alreadyLogged = CaseEvent::query()
            ->where('case_id', $this->case->id)
            ->where('type', 'history_incorporated')
            ->exists();

        if (! $alreadyLogged) {
            CaseEvent::create([
                'case_id' => $this->case->id,
                'type' => 'history_incorporated',
                'description' => 'Registro de historial #'.$entry->id.' creado desde el caso',
                'created_by' => Auth::id(),
                'created_at' => now(),
            ]);
        }

        $this->case->load(['events.author', 'incorporatedHistoryEntry']);
        session()->flash('message', 'Resultado incorporado al historial de la sede.');
    }

    public function advanceStage(): void
    {
        try {
            $this->case = app(AdvanceCaseStage::class)->handle($this->case, Auth::user());
            $this->case->load(['currentStage.requirements', 'stageStatuses.stage', 'events.author', 'closedByUser']);
            session()->flash('message', 'Etapa avanzada correctamente.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first());
        }
    }

    public function cancelCase(): void
    {
        if (! CaseOperationalAccess::canManage()) {
            abort(403, 'No tiene permiso para cancelar casos.');
        }

        $this->case->update([
            'status' => CaseStatus::Cancelled,
            'closed_at' => now(),
            'closed_by' => Auth::id(),
            'current_stage_id' => null,
        ]);

        CaseEvent::create([
            'case_id' => $this->case->id,
            'type' => 'case_cancelled',
            'description' => 'Caso cancelado',
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);

        if ($this->case->agenda) {
            app(RecalculateAgendaStatus::class)->handle($this->case->agenda);
        }

        $this->case->refresh()->load(['currentStage.requirements', 'stageStatuses.stage', 'closedByUser']);
        session()->flash('message', 'Caso cancelado.');
    }

    public function getSpecialistsProperty()
    {
        return Party::query()
            ->whereHas('actorType', fn ($q) => $q->where('category', 'specialist'))
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();
    }

    /**
     * @return Collection<int, WorkflowStage>
     */
    public function getOrderedStagesProperty(): Collection
    {
        return CaseWorkspaceStages::orderedStages($this->case);
    }

    public function stageState(WorkflowStage $stage): string
    {
        return CaseWorkspaceStages::stageState($this->case, $stage);
    }

    public function sectionState(string $section): string
    {
        return CaseWorkspaceStages::sectionState($this->case, $section);
    }

    public function canEditSection(string $section): bool
    {
        return CaseWorkspaceStages::canEditSection($this->case, $section);
    }

    public function canConfirmPayments(): bool
    {
        return Auth::user()?->can('payments.confirm') ?? false;
    }

    public function canAdvanceStage(): bool
    {
        if ($this->case->status !== CaseStatus::Open) {
            return false;
        }

        if ($this->case->currentStage === null) {
            return false;
        }

        return ! (bool) $this->case->currentStage->is_terminal;
    }

    public function canFinalizeConsultation(): bool
    {
        return $this->case->status === CaseStatus::Open
            && (bool) $this->case->currentStage?->is_terminal
            && $this->canEditSection('consultation');
    }

    public function canCancelCase(): bool
    {
        return CaseOperationalAccess::canManage() && $this->case->status === CaseStatus::Open;
    }

    public function canManageAttachments(): bool
    {
        return $this->case->status === CaseStatus::Open;
    }

    public function getAttachmentsProperty()
    {
        return $this->case->getMedia('attachments');
    }

    public function getSignedConstanciesProperty()
    {
        return $this->case->getMedia('signed_constancy');
    }

    public function getSharedHistoryEntriesProperty()
    {
        return HistoryAccess::sharedEntriesForCase($this->case);
    }

    public function getCanIncorporateHistoryProperty(): bool
    {
        if ($this->case->subject === null || $this->case->status !== CaseStatus::Closed) {
            return false;
        }

        $this->case->subject->loadMissing('organization.network');

        if (! HistoryAccess::canManageSubject(Auth::user(), $this->case->subject)) {
            return false;
        }

        return $this->case->incorporatedHistoryEntry()->doesntExist();
    }

    /**
     * @return Collection<int, array{at: \Illuminate\Support\Carbon|null, label: string, description: string, author: string}>
     */
    public function getAuditEntriesProperty(): Collection
    {
        $events = $this->case->events->map(fn (CaseEvent $event) => [
            'at' => $event->created_at,
            'label' => CaseEventLabels::label($event->type),
            'description' => $event->description ?? '',
            'author' => $event->author?->name ?? 'Sistema',
        ]);

        $activities = Activity::forSubject($this->case)
            ->latest()
            ->with('causer')
            ->get()
            ->map(fn (Activity $activity) => [
                'at' => $activity->created_at,
                'label' => 'Cambio en '.terminology('case', 'caso'),
                'description' => $activity->description ?? collect($activity->properties['attributes'] ?? [])->map(
                    fn ($value, $key) => "{$key}: {$value}",
                )->implode(', '),
                'author' => $activity->causer?->name ?? 'Sistema',
            ]);

        return $events
            ->concat($activities)
            ->sortByDesc('at')
            ->values();
    }

    protected function findRequirementId(string $key): ?int
    {
        return StageRequirement::query()
            ->whereHas('stage', fn ($q) => $q->where('workflow_template_id', $this->case->workflow_template_id))
            ->where('key', $key)
            ->value('id');
    }

    public function render()
    {
        return view('livewire.case-workspace')
            ->layout('layouts.app');
    }
}
