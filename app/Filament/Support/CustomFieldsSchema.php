<?php

namespace App\Filament\Support;

use App\Enums\CustomFieldType;
use App\Models\CustomFieldDefinition;
use App\Support\Tenancy\NetworkContext;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Get;

class CustomFieldsSchema
{
    /**
     * @return array<int, Component>
     */
    public static function fields(
        string $entityType,
        ?int $actorTypeId = null,
        ?int $networkId = null,
    ): array {
        $networkId ??= auth()->user()?->network_id ?? NetworkContext::id();

        if ($networkId === null) {
            return [];
        }

        $definitions = CustomFieldDefinition::query()
            ->withoutGlobalScopes()
            ->where('network_id', $networkId)
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->when(
                $entityType === 'party',
                fn ($query) => $actorTypeId
                    ? $query->where(function ($inner) use ($actorTypeId): void {
                        $inner->whereNull('actor_type_id')
                            ->orWhere('actor_type_id', $actorTypeId);
                    })
                    : $query->whereNull('actor_type_id'),
            )
            ->orderBy('sort_order')
            ->get();

        return $definitions->map(fn (CustomFieldDefinition $field) => self::mapField($field))->all();
    }

    public static function section(
        string $entityType,
        ?callable $actorTypeGetter = null,
        ?callable $networkIdGetter = null,
    ): Forms\Components\Section {
        return Forms\Components\Section::make('Datos adicionales (configurables)')
            ->schema(function (Get $get) use ($entityType, $actorTypeGetter, $networkIdGetter): array {
                $actorTypeId = $actorTypeGetter ? (int) $actorTypeGetter($get) ?: null : null;
                $networkId = $networkIdGetter ? (int) $networkIdGetter($get) ?: null : null;

                return self::fields($entityType, $actorTypeId, $networkId);
            })
            ->columnSpanFull()
            ->collapsible()
            ->collapsed(false);
    }

    protected static function mapField(CustomFieldDefinition $field): Component
    {
        $name = "metadata.{$field->key}";
        $label = $field->label;
        $required = $field->is_required;
        $helper = $field->help_text;

        $component = match ($field->field_type) {
            CustomFieldType::Textarea => Forms\Components\Textarea::make($name)->rows(3),
            CustomFieldType::Number => Forms\Components\TextInput::make($name)->numeric(),
            CustomFieldType::Date => Forms\Components\DatePicker::make($name),
            CustomFieldType::Datetime => Forms\Components\DateTimePicker::make($name),
            CustomFieldType::Select => Forms\Components\Select::make($name)
                ->options(collect($field->options['values'] ?? [])->mapWithKeys(fn ($v) => [$v => $v])->all()),
            CustomFieldType::Multiselect => Forms\Components\Select::make($name)
                ->multiple()
                ->options(collect($field->options['values'] ?? [])->mapWithKeys(fn ($v) => [$v => $v])->all()),
            CustomFieldType::Checkbox => Forms\Components\Toggle::make($name),
            CustomFieldType::File => Forms\Components\FileUpload::make($name)
                ->directory('custom-fields')
                ->storeFileNamesIn("metadata.{$field->key}_filename"),
            default => Forms\Components\TextInput::make($name),
        };

        return $component
            ->label($label)
            ->helperText($helper)
            ->required($required);
    }
}
