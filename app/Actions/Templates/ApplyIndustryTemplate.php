<?php

namespace App\Actions\Templates;

use App\Domain\Templates\IndustryTemplateData;
use App\Domain\Templates\IndustryTemplateRegistry;
use App\Models\ActorType;
use App\Models\CustomFieldDefinition;
use App\Models\HistoryEntryType;
use App\Models\Network;
use App\Models\StageRequirement;
use App\Models\Terminology;
use App\Models\WorkflowStage;
use App\Models\WorkflowTemplate;
use App\Support\TerminologyHelper;
use Illuminate\Support\Facades\DB;

class ApplyIndustryTemplate
{
    public function handle(Network $network, ?string $templateKey = null): void
    {
        $templateKey ??= $network->industry_template_key;
        $data = $this->resolveTemplate($templateKey);

        DB::transaction(function () use ($network, $data): void {
            $network->update([
                'industry_template_key' => $data->key,
                'settings' => $data->settingsWithAgendaDefaults(),
            ]);

            Terminology::query()->where('network_id', $network->id)->delete();
            ActorType::query()->where('network_id', $network->id)->delete();
            CustomFieldDefinition::query()->where('network_id', $network->id)->delete();

            WorkflowTemplate::query()
                ->where('network_id', $network->id)
                ->each(function (WorkflowTemplate $template): void {
                    $template->stages()->each(fn (WorkflowStage $stage) => $stage->requirements()->delete());
                    $template->stages()->delete();
                    $template->delete();
                });

            foreach ($data->terminology as $row) {
                Terminology::create([
                    'network_id' => $network->id,
                    ...$row,
                ]);
            }

            foreach ($data->actorTypes as $index => $row) {
                ActorType::create([
                    'network_id' => $network->id,
                    'sort_order' => $index,
                    ...$row,
                ]);
            }

            $workflow = WorkflowTemplate::create([
                'network_id' => $network->id,
                'name' => $data->workflow['name'],
                'is_default' => true,
                'is_active' => true,
                'instructions' => $data->workflow['instructions'] ?? null,
                'consent_text' => $data->workflow['consent_text'] ?? null,
            ]);

            foreach ($data->workflow['stages'] as $stageIndex => $stageData) {
                $stage = WorkflowStage::create([
                    'workflow_template_id' => $workflow->id,
                    'key' => $stageData['key'],
                    'label' => $stageData['label'],
                    'description' => $stageData['description'] ?? null,
                    'sort_order' => $stageIndex,
                    'is_terminal' => $stageData['is_terminal'] ?? false,
                ]);

                foreach ($stageData['requirements'] ?? [] as $reqIndex => $requirement) {
                    StageRequirement::create([
                        'workflow_stage_id' => $stage->id,
                        'key' => $requirement['key'],
                        'label' => $requirement['label'],
                        'type' => $requirement['type'],
                        'is_mandatory' => $requirement['is_mandatory'] ?? true,
                        'config' => $requirement['config'] ?? null,
                        'sort_order' => $reqIndex,
                    ]);
                }
            }

            foreach ($data->customFields as $index => $field) {
                CustomFieldDefinition::create([
                    'network_id' => $network->id,
                    'sort_order' => $index,
                    'is_active' => true,
                    'is_required' => $field['is_required'] ?? false,
                    ...$field,
                ]);
            }

            foreach ($data->historyEntryTypes as $index => $type) {
                HistoryEntryType::updateOrCreate(
                    [
                        'network_id' => $network->id,
                        'key' => $type['key'],
                    ],
                    [
                        'label' => $type['label'],
                        'description' => $type['description'] ?? null,
                        'field_schema' => $type['field_schema'] ?? null,
                        'sort_order' => $index,
                        'is_active' => true,
                    ],
                );
            }

            TerminologyHelper::clearCache($network->id);
        });
    }

    protected function resolveTemplate(?string $key): IndustryTemplateData
    {
        return app(IndustryTemplateRegistry::class)->find($key);
    }
}
