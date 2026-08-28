<?php

namespace App\Livewire;

use App\Enums\CustomFieldType;
use App\Models\CustomFieldDefinition;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use Livewire\WithFileUploads;

class CustomFieldsForm extends Component
{
    use WithFileUploads;

    public string $entityType;

    public ?int $actorTypeId = null;

    #[Modelable]
    public array $metadata = [];

    public array $uploads = [];

    public function mount(string $entityType, array $metadata = [], ?int $actorTypeId = null): void
    {
        $this->entityType = $entityType;
        $this->metadata = $metadata;
        $this->actorTypeId = $actorTypeId;
    }

    public function getDefinitionsProperty()
    {
        return CustomFieldDefinition::query()
            ->where('entity_type', $this->entityType)
            ->where('is_active', true)
            ->when($this->actorTypeId, fn ($q) => $q->where(function ($query): void {
                $query->whereNull('actor_type_id')
                    ->orWhere('actor_type_id', $this->actorTypeId);
            }))
            ->orderBy('sort_order')
            ->get();
    }

    public function rules(): array
    {
        $rules = [];

        foreach ($this->definitions as $definition) {
            $key = "metadata.{$definition->key}";
            $rules[$key] = $definition->is_required ? 'required' : 'nullable';

            if ($definition->field_type === CustomFieldType::Number) {
                $rules[$key] .= '|numeric';
            }
        }

        return $rules;
    }

    public function render()
    {
        return view('livewire.custom-fields-form');
    }
}
