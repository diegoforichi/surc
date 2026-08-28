<?php

namespace Tests\Feature;

use App\Actions\Templates\ApplyIndustryTemplate;
use App\Domain\Templates\IndustryTemplateRegistry;
use App\Domain\Templates\InvalidIndustryTemplateException;
use App\Models\CustomFieldDefinition;
use App\Models\HistoryEntryType;
use App\Models\Network;
use App\Models\StageRequirement;
use App\Models\Terminology;
use App\Models\WorkflowStage;
use App\Support\Settings\NetworkSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IndustryTemplateRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_discovers_bundled_packs(): void
    {
        $registry = app(IndustryTemplateRegistry::class);
        $options = $registry->options();

        $this->assertArrayHasKey('veterinary', $options);
        $this->assertArrayHasKey('grooming', $options);
        $this->assertArrayHasKey('generic', $options);
        $this->assertSame('Veterinaria', $options['veterinary']);
        $this->assertSame('Genérico', $options['generic']);
    }

    public function test_unknown_key_falls_back_to_generic(): void
    {
        $pack = app(IndustryTemplateRegistry::class)->find('taller-inexistente');

        $this->assertSame('generic', $pack->key);
        $this->assertSame('Genérico', $pack->name);
    }

    public function test_invalid_pack_reports_file_and_missing_field(): void
    {
        $directory = $this->makePacksDirectory();
        File::put($directory.DIRECTORY_SEPARATOR.'broken.json', json_encode([
            'name' => 'Roto',
            'terminology' => [],
            'actor_types' => [],
            'workflow' => ['name' => 'Flujo', 'stages' => []],
        ], JSON_THROW_ON_ERROR));

        $this->usePacksDirectory($directory);

        $this->expectException(InvalidIndustryTemplateException::class);
        $this->expectExceptionMessage("El pack 'broken.json' no tiene el campo obligatorio 'key'.");

        app(IndustryTemplateRegistry::class)->all();
    }

    public function test_invalid_json_reports_file(): void
    {
        $directory = $this->makePacksDirectory();
        File::put($directory.DIRECTORY_SEPARATOR.'invalid.json', '{no json');

        $this->usePacksDirectory($directory);

        $this->expectException(InvalidIndustryTemplateException::class);
        $this->expectExceptionMessage("El pack 'invalid.json' no es JSON válido");

        app(IndustryTemplateRegistry::class)->all();
    }

    public function test_veterinary_pack_seeds_expected_counts_and_agenda_defaults(): void
    {
        $pack = app(IndustryTemplateRegistry::class)->find('veterinary');
        $network = Network::create([
            'name' => 'Red Vet',
            'slug' => 'red-vet-counts',
            'industry_template_key' => 'generic',
            'is_active' => true,
        ]);

        app(ApplyIndustryTemplate::class)->handle($network, 'veterinary');

        $this->assertSame('veterinary', $network->fresh()->industry_template_key);
        $this->assertCount(count($pack->terminology), Terminology::query()->where('network_id', $network->id)->get());
        $this->assertCount(count($pack->customFields), CustomFieldDefinition::query()->where('network_id', $network->id)->get());
        $this->assertCount(count($pack->workflow['stages']), WorkflowStage::query()->whereHas(
            'template',
            fn ($query) => $query->where('network_id', $network->id),
        )->get());

        $expectedRequirements = collect($pack->workflow['stages'])->sum(
            fn (array $stage): int => count($stage['requirements'] ?? []),
        );
        $this->assertSame($expectedRequirements, StageRequirement::query()->whereHas(
            'stage.template',
            fn ($query) => $query->where('network_id', $network->id),
        )->count());

        $this->assertTrue(
            Terminology::query()
                ->where('network_id', $network->id)
                ->where('entity_key', 'ux.case_diagnosis')
                ->where('label', 'Diagnóstico')
                ->exists()
        );

        $defaults = NetworkSettings::agendaDefaults($network->fresh());
        $this->assertSame(30, $defaults['slot_minutes']);
        $this->assertSame('09:00', $defaults['start_time']);
        $this->assertNotEmpty($defaults['instructions']);
        $this->assertNotEmpty($defaults['consent_text']);
    }

    public function test_generic_pack_does_not_seed_industry_specific_history_fields(): void
    {
        $network = Network::create([
            'name' => 'Red Genérica',
            'slug' => 'red-generica-pack',
            'industry_template_key' => 'generic',
            'is_active' => true,
        ]);

        app(ApplyIndustryTemplate::class)->handle($network, 'generic');

        $this->assertTrue(HistoryEntryType::query()->where('network_id', $network->id)->where('key', 'note')->exists());
        $this->assertTrue(HistoryEntryType::query()->where('network_id', $network->id)->where('key', 'intervention')->exists());
        $this->assertTrue(HistoryEntryType::query()->where('network_id', $network->id)->where('key', 'follow_up')->exists());
        $this->assertFalse(HistoryEntryType::query()->where('network_id', $network->id)->where('key', 'inspection')->exists());

        $intervention = HistoryEntryType::query()->where('network_id', $network->id)->where('key', 'intervention')->first();
        $this->assertSame([], $intervention?->field_schema ?? []);
    }

    protected function makePacksDirectory(): string
    {
        $directory = storage_path('framework/testing/industry-packs-'.uniqid());
        File::ensureDirectoryExists($directory);

        $this->beforeApplicationDestroyed(function () use ($directory): void {
            File::deleteDirectory($directory);
        });

        return $directory;
    }

    protected function usePacksDirectory(string $directory): void
    {
        config(['surc.industry_packs_path' => $directory]);
        app(IndustryTemplateRegistry::class)->flush();
    }
}
