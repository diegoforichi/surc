<?php

namespace App\Actions\Templates;

use App\Domain\Templates\IndustryTemplateData;
use App\Models\Network;
use App\Models\WorkflowTemplate;
use BackedEnum;
use Illuminate\Support\Str;

class ExportIndustryTemplate
{
    public function handle(Network $network, ?string $key = null, ?string $name = null): IndustryTemplateData
    {
        $network->load([
            'terminology',
            'actorTypes',
            'workflowTemplates.stages.requirements',
        ]);

        $workflow = $this->defaultWorkflow($network);
        $settings = is_array($network->settings) ? $network->settings : [];
        $agenda = is_array($settings['agenda']['defaults'] ?? null)
            ? $settings['agenda']['defaults']
            : [];

        if (isset($settings['agenda']) && is_array($settings['agenda'])) {
            unset($settings['agenda']['defaults']);
        }

        if ($agenda === []) {
            $agenda = [
                'slot_minutes' => 30,
                'start_time' => '09:00',
                'instructions' => $workflow?->instructions,
                'consent_text' => $workflow?->consent_text,
            ];
        }

        return IndustryTemplateData::fromArray([
            'key' => $key ?: ($network->industry_template_key ?: Str::slug($network->name)),
            'name' => $name ?: $network->name,
            'terminology' => $network->terminology()
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => $this->withoutNulls([
                    'entity_key' => $row->entity_key,
                    'label' => $row->label,
                    'label_plural' => $row->label_plural,
                    'description' => $row->description,
                ]))
                ->values()
                ->all(),
            'actor_types' => $network->actorTypes()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($type) => $this->withoutNulls([
                    'key' => $type->key,
                    'label' => $type->label,
                    'category' => $this->enumValue($type->category),
                    'is_user_linkable' => (bool) $type->is_user_linkable,
                    'show_in_directory' => (bool) $type->show_in_directory,
                ]))
                ->values()
                ->all(),
            'workflow' => [
                'name' => $workflow?->name ?? 'Flujo estándar',
                'instructions' => $workflow?->instructions,
                'consent_text' => $workflow?->consent_text,
                'stages' => $workflow
                    ? $workflow->stages->map(fn ($stage) => $this->withoutNulls([
                        'key' => $stage->key,
                        'label' => $stage->label,
                        'description' => $stage->description,
                        'is_terminal' => $stage->is_terminal ? true : null,
                        'requirements' => $stage->requirements->map(fn ($requirement) => $this->withoutNulls([
                            'key' => $requirement->key,
                            'label' => $requirement->label,
                            'type' => $this->enumValue($requirement->type),
                            'is_mandatory' => $requirement->is_mandatory,
                            'config' => $requirement->config,
                        ]))->values()->all(),
                    ]))->values()->all()
                    : [],
            ],
            'custom_fields' => $network->customFieldDefinitions()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($field) => $this->withoutNulls([
                    'entity_type' => $field->entity_type,
                    'key' => $field->key,
                    'label' => $field->label,
                    'help_text' => $field->help_text,
                    'field_type' => $this->enumValue($field->field_type),
                    'options' => $field->options,
                    'is_required' => $field->is_required ? true : null,
                ]))
                ->values()
                ->all(),
            'settings' => $settings,
            'history_entry_types' => $network->historyEntryTypes()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($type) => $this->withoutNulls([
                    'key' => $type->key,
                    'label' => $type->label,
                    'description' => $type->description,
                    'field_schema' => $type->field_schema,
                ]))
                ->values()
                ->all(),
            'agenda' => $this->withoutNulls([
                'slot_minutes' => (int) ($agenda['slot_minutes'] ?? 30),
                'start_time' => $agenda['start_time'] ?? '09:00',
                'instructions' => $agenda['instructions'] ?? $workflow?->instructions,
                'consent_text' => $agenda['consent_text'] ?? $workflow?->consent_text,
            ]),
        ]);
    }

    protected function defaultWorkflow(Network $network): ?WorkflowTemplate
    {
        return $network->workflowTemplates
            ->firstWhere('is_default', true)
            ?? $network->workflowTemplates->first();
    }

    protected function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function withoutNulls(array $row): array
    {
        return array_filter($row, fn (mixed $value) => $value !== null && $value !== '');
    }
}
