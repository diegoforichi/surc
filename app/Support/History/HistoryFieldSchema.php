<?php

namespace App\Support\History;

use App\Enums\CustomFieldType;
use Filament\Forms;
use Filament\Forms\Components\Component;

class HistoryFieldSchema
{
    /**
     * @var list<string>
     */
    public const TYPES = [
        'text',
        'textarea',
        'number',
        'date',
        'datetime',
        'select',
        'multiselect',
        'checkbox',
    ];

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return collect(self::TYPES)
            ->mapWithKeys(fn (string $type): array => [
                $type => CustomFieldType::from($type)->label(),
            ])
            ->all();
    }

    /**
     * @param  mixed  $schema
     * @return list<array{key: string, label: string, type: string, required: bool, options: list<string>}>
     */
    public static function normalize(mixed $schema): array
    {
        if (! is_array($schema)) {
            return [];
        }

        $normalized = [];
        $seen = [];

        foreach ($schema as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = strtolower(trim((string) ($field['key'] ?? '')));

            if ($key === '' || isset($seen[$key]) || preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) !== 1) {
                continue;
            }

            $type = (string) ($field['type'] ?? 'text');

            if (! in_array($type, self::TYPES, true)) {
                $type = 'text';
            }

            $options = $field['options'] ?? [];

            if (! is_array($options)) {
                $options = [];
            }

            $options = array_values(array_filter(
                array_map(fn ($option): string => trim((string) $option), $options),
                fn (string $option): bool => $option !== '',
            ));

            $seen[$key] = true;
            $normalized[] = [
                'key' => $key,
                'label' => filled($field['label'] ?? null) ? (string) $field['label'] : $key,
                'type' => $type,
                'required' => (bool) ($field['required'] ?? false),
                'options' => $options,
            ];
        }

        return $normalized;
    }

    /**
     * @param  mixed  $schema
     * @return array<int, Component>
     */
    public static function formFields(mixed $schema): array
    {
        return collect(self::normalize($schema))
            ->map(fn (array $field): Component => self::mapField($field))
            ->all();
    }

    /**
     * @param  mixed  $schema
     * @param  mixed  $payload
     * @return array<string, mixed>
     */
    public static function extractPayload(mixed $schema, mixed $payload): array
    {
        $values = is_array($payload) ? $payload : [];
        $extracted = [];

        foreach (self::normalize($schema) as $field) {
            $key = $field['key'];

            if (! array_key_exists($key, $values)) {
                continue;
            }

            $extracted[$key] = $values[$key];
        }

        return $extracted;
    }

    /**
     * @param  mixed  $schema
     * @param  mixed  $payload
     * @return list<array{label: string, value: string}>
     */
    public static function displayPairs(mixed $schema, mixed $payload): array
    {
        $values = is_array($payload) ? $payload : [];
        $pairs = [];

        foreach (self::normalize($schema) as $field) {
            $raw = $values[$field['key']] ?? null;
            $pairs[] = [
                'label' => $field['label'],
                'value' => self::displayValue($raw),
            ];
        }

        return $pairs;
    }

    protected static function displayValue(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '—';
        }

        if (is_bool($raw)) {
            return $raw ? 'Sí' : 'No';
        }

        if (is_array($raw)) {
            $items = array_filter(array_map(fn ($item): string => trim((string) $item), $raw));

            return $items === [] ? '—' : implode(', ', $items);
        }

        return (string) $raw;
    }

    /**
     * @param  array{key: string, label: string, type: string, required: bool, options: list<string>}  $field
     */
    protected static function mapField(array $field): Component
    {
        $name = 'payload.'.$field['key'];
        $options = collect($field['options'])->mapWithKeys(fn (string $option): array => [$option => $option])->all();

        $component = match ($field['type']) {
            'textarea' => Forms\Components\Textarea::make($name)->rows(3),
            'number' => Forms\Components\TextInput::make($name)->numeric(),
            'date' => Forms\Components\DatePicker::make($name),
            'datetime' => Forms\Components\DateTimePicker::make($name),
            'select' => Forms\Components\Select::make($name)->options($options),
            'multiselect' => Forms\Components\Select::make($name)->multiple()->options($options),
            'checkbox' => Forms\Components\Toggle::make($name),
            default => Forms\Components\TextInput::make($name),
        };

        return $component
            ->label($field['label'])
            ->required($field['required']);
    }
}
