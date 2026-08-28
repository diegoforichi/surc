<?php

namespace Tests\Unit;

use App\Support\History\HistoryFieldSchema;
use PHPUnit\Framework\TestCase;

class HistoryFieldSchemaTest extends TestCase
{
    public function test_it_normalizes_valid_fields_and_drops_invalid_keys(): void
    {
        $schema = HistoryFieldSchema::normalize([
            ['key' => 'Weight', 'label' => 'Peso', 'type' => 'number', 'required' => true],
            ['key' => '1bad', 'label' => 'No', 'type' => 'text'],
            ['key' => 'notes', 'label' => 'Notas', 'type' => 'unknown'],
            ['key' => 'weight', 'label' => 'Duplicado', 'type' => 'text'],
            'not-an-array',
        ]);

        $this->assertCount(2, $schema);
        $this->assertSame('weight', $schema[0]['key']);
        $this->assertSame('number', $schema[0]['type']);
        $this->assertTrue($schema[0]['required']);
        $this->assertSame('notes', $schema[1]['key']);
        $this->assertSame('text', $schema[1]['type']);
    }

    public function test_it_extracts_only_schema_keys_from_payload(): void
    {
        $schema = [
            ['key' => 'mileage', 'label' => 'Km', 'type' => 'number'],
        ];

        $this->assertSame(
            ['mileage' => 1200],
            HistoryFieldSchema::extractPayload($schema, ['mileage' => 1200, 'hack' => 'no']),
        );
    }

    public function test_it_formats_display_pairs(): void
    {
        $pairs = HistoryFieldSchema::displayPairs(
            [
                ['key' => 'ok', 'label' => 'Listo', 'type' => 'checkbox'],
                ['key' => 'tags', 'label' => 'Etiquetas', 'type' => 'multiselect', 'options' => ['a', 'b']],
            ],
            ['ok' => true, 'tags' => ['a', 'b']],
        );

        $this->assertSame('Listo', $pairs[0]['label']);
        $this->assertSame('Sí', $pairs[0]['value']);
        $this->assertSame('a, b', $pairs[1]['value']);
    }

    public function test_it_detects_missing_required_fields_and_proposes_summary(): void
    {
        $schema = [
            ['key' => 'findings', 'label' => 'Hallazgos', 'type' => 'textarea', 'required' => true],
            ['key' => 'weight', 'label' => 'Peso', 'type' => 'number'],
        ];

        $this->assertSame(['Hallazgos'], HistoryFieldSchema::missingRequired($schema, ['weight' => 12]));
        $this->assertSame([], HistoryFieldSchema::missingRequired($schema, ['findings' => 'Otitis']));
        $this->assertSame('Otitis', HistoryFieldSchema::proposedSummary($schema, ['findings' => 'Otitis']));
        $this->assertSame(['weight' => 12], HistoryFieldSchema::reusableValues($schema, [
            'weight' => 12,
            'findings' => 'Otitis',
        ]));
    }
}
