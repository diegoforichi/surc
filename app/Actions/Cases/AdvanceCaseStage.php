<?php

namespace App\Actions\Cases;

use App\Actions\Agendas\RecalculateAgendaStatus;
use App\Enums\CaseStatus;
use App\Enums\RequirementType;
use App\Models\CaseEvent;
use App\Models\CaseRecord;
use App\Models\CaseRequirementCompletion;
use App\Models\CaseStageStatus;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdvanceCaseStage
{
    public function handle(CaseRecord $case, ?User $user = null): CaseRecord
    {
        $currentStage = $case->currentStage;

        if ($currentStage === null) {
            throw ValidationException::withMessages([
                'stage' => 'El caso no tiene una etapa actual asignada.',
            ]);
        }

        $this->assertRequirementsMet($case, $currentStage);

        $nextStage = WorkflowStage::query()
            ->where('workflow_template_id', $case->workflow_template_id)
            ->where('sort_order', '>', $currentStage->sort_order)
            ->orderBy('sort_order')
            ->first();

        return DB::transaction(function () use ($case, $currentStage, $nextStage, $user): CaseRecord {
            CaseStageStatus::updateOrCreate(
                ['case_id' => $case->id, 'workflow_stage_id' => $currentStage->id],
                ['status' => 'done', 'completed_by' => $user?->id, 'completed_at' => now()]
            );

            CaseEvent::create([
                'case_id' => $case->id,
                'type' => 'stage_completed',
                'description' => "Etapa completada: {$currentStage->label}",
                'created_by' => $user?->id,
                'created_at' => now(),
            ]);

            if ($nextStage === null) {
                $case->update([
                    'status' => CaseStatus::Closed,
                    'closed_at' => now(),
                    'closed_by' => $user?->id,
                    'current_stage_id' => null,
                ]);

                CaseEvent::create([
                    'case_id' => $case->id,
                    'type' => 'case_closed',
                    'description' => 'Caso atendido y cerrado',
                    'created_by' => $user?->id,
                    'created_at' => now(),
                ]);

                if ($case->agenda) {
                    app(RecalculateAgendaStatus::class)->handle($case->agenda);
                }

                return $case->fresh();
            }

            $case->update(['current_stage_id' => $nextStage->id]);

            CaseStageStatus::updateOrCreate(
                ['case_id' => $case->id, 'workflow_stage_id' => $nextStage->id],
                ['status' => 'in_progress']
            );

            CaseEvent::create([
                'case_id' => $case->id,
                'type' => 'stage_started',
                'description' => "Etapa iniciada: {$nextStage->label}",
                'created_by' => $user?->id,
                'created_at' => now(),
            ]);

            return $case->fresh();
        });
    }

    protected function assertRequirementsMet(CaseRecord $case, WorkflowStage $stage): void
    {
        foreach ($stage->requirements()->where('is_mandatory', true)->get() as $requirement) {
            if ($requirement->type === RequirementType::Payment) {
                $hasConfirmed = $case->payments()->where('status', 'confirmed')->exists();
                if (! $hasConfirmed) {
                    throw ValidationException::withMessages([
                        'payment' => 'Debe confirmar el pago/seña antes de avanzar.',
                    ]);
                }

                continue;
            }

            $completion = CaseRequirementCompletion::query()
                ->where('case_id', $case->id)
                ->where('stage_requirement_id', $requirement->id)
                ->first();

            if ($requirement->type === RequirementType::Field) {
                if ($requirement->key === 'summary_loaded' && blank($case->summary)) {
                    throw ValidationException::withMessages([
                        'summary' => 'Debe cargar la ficha resumida antes de avanzar.',
                    ]);
                }

                if ($requirement->key === 'technical_responsible') {
                    $hasResponsible = $case->events()
                        ->where('type', 'consultation')
                        ->where(function ($query): void {
                            $query->whereNotNull('technical_responsible_party_id')
                                ->orWhereNotNull('technical_responsible_name');
                        })
                        ->exists();

                    if (! $hasResponsible) {
                        throw ValidationException::withMessages([
                            'technical_responsible' => 'Debe registrar el técnico interviniente.',
                        ]);
                    }
                }

                continue;
            }

            if (! $completion?->is_completed) {
                throw ValidationException::withMessages([
                    'requirements' => "Requisito pendiente: {$requirement->label}",
                ]);
            }
        }
    }
}
