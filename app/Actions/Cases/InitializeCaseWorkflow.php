<?php

namespace App\Actions\Cases;

use App\Models\CaseRecord;
use App\Models\CaseStageStatus;
use App\Models\WorkflowTemplate;

class InitializeCaseWorkflow
{
    public function handle(CaseRecord $case): CaseRecord
    {
        $template = $case->workflowTemplate;

        if ($template === null) {
            $template = WorkflowTemplate::query()
                ->where('network_id', $case->network_id)
                ->where('is_default', true)
                ->first();
        }

        if ($template === null) {
            $template = WorkflowTemplate::query()
                ->where('network_id', $case->network_id)
                ->where('is_active', true)
                ->first();
        }

        $firstStage = $template?->stages()->orderBy('sort_order')->first();

        $updateData = [
            'current_stage_id' => $firstStage?->id,
            'opened_at' => $case->opened_at ?? now(),
        ];

        if (blank($case->workflow_template_id)) {
            $updateData['workflow_template_id'] = $template?->id;
        }

        $case->update($updateData);

        if ($firstStage) {
            CaseStageStatus::updateOrCreate(
                ['case_id' => $case->id, 'workflow_stage_id' => $firstStage->id],
                ['status' => 'in_progress']
            );
        }

        return $case->fresh();
    }
}
