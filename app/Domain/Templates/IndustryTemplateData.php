<?php

namespace App\Domain\Templates;

class IndustryTemplateData
{
    /**
     * @param  array<int, array<string, mixed>>  $terminology
     * @param  array<int, array<string, mixed>>  $actorTypes
     * @param  array<string, mixed>  $workflow
     * @param  array<int, array<string, mixed>>  $customFields
     * @param  array<string, mixed>  $settings
     * @param  array<int, array<string, mixed>>  $historyEntryTypes
     * @param  array<string, mixed>  $agenda
     */
    public function __construct(
        public string $key,
        public string $name,
        public array $terminology,
        public array $actorTypes,
        public array $workflow,
        public array $customFields = [],
        public array $settings = [],
        public array $historyEntryTypes = [],
        public array $agenda = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            key: (string) ($payload['key'] ?? ''),
            name: (string) ($payload['name'] ?? ''),
            terminology: is_array($payload['terminology'] ?? null) ? $payload['terminology'] : [],
            actorTypes: is_array($payload['actor_types'] ?? null) ? $payload['actor_types'] : [],
            workflow: is_array($payload['workflow'] ?? null) ? $payload['workflow'] : [],
            customFields: is_array($payload['custom_fields'] ?? null) ? $payload['custom_fields'] : [],
            settings: is_array($payload['settings'] ?? null) ? $payload['settings'] : [],
            historyEntryTypes: is_array($payload['history_entry_types'] ?? null) ? $payload['history_entry_types'] : [],
            agenda: is_array($payload['agenda'] ?? null) ? $payload['agenda'] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'terminology' => $this->terminology,
            'actor_types' => $this->actorTypes,
            'workflow' => $this->workflow,
            'custom_fields' => $this->customFields,
            'settings' => $this->settings,
            'history_entry_types' => $this->historyEntryTypes,
            'agenda' => $this->agenda,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsWithAgendaDefaults(): array
    {
        $settings = $this->settings;
        $settings['agenda'] = array_replace_recursive($settings['agenda'] ?? [], [
            'defaults' => array_replace([
                'slot_minutes' => 30,
                'start_time' => '09:00',
                'instructions' => null,
                'consent_text' => null,
            ], $this->agenda),
        ]);

        return $settings;
    }
}
