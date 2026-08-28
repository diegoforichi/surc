<?php

namespace Tests\Feature;

use App\Actions\Templates\ApplyIndustryTemplate;
use App\Domain\Templates\IndustryTemplateRegistry;
use App\Models\ActorType;
use App\Models\CustomFieldDefinition;
use App\Models\HistoryEntryType;
use App\Models\Network;
use App\Models\Terminology;
use App\Models\WorkflowStage;
use App\Models\WorkflowTemplate;
use App\Support\Settings\NetworkSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApplyIndustryTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_grooming_template_configures_network_without_code_changes(): void
    {
        $network = Network::create([
            'name' => 'Test Grooming',
            'slug' => 'test-grooming',
            'industry_template_key' => 'generic',
            'is_active' => true,
        ]);

        app(ApplyIndustryTemplate::class)->handle($network, 'grooming');

        $this->assertEquals('grooming', $network->fresh()->industry_template_key);
        $this->assertTrue(Terminology::where('network_id', $network->id)->where('entity_key', 'subject')->where('label', 'Mascota')->exists());
        $this->assertTrue(ActorType::where('network_id', $network->id)->where('key', 'specialist')->exists());
        $this->assertTrue(WorkflowTemplate::where('network_id', $network->id)->where('is_default', true)->exists());
        $this->assertTrue(HistoryEntryType::where('network_id', $network->id)->where('key', 'service_note')->exists());
        $this->assertNotEmpty(
            HistoryEntryType::query()
                ->where('network_id', $network->id)
                ->where('key', 'incident')
                ->value('field_schema')
        );
    }

    public function test_generic_and_veterinary_templates_seed_history_field_schemas(): void
    {
        $network = Network::create([
            'name' => 'Test Schemas',
            'slug' => 'test-schemas',
            'industry_template_key' => 'generic',
            'is_active' => true,
        ]);

        app(ApplyIndustryTemplate::class)->handle($network, 'generic');
        $this->assertTrue(HistoryEntryType::query()->where('network_id', $network->id)->where('key', 'note')->exists());
        $this->assertFalse(HistoryEntryType::query()->where('network_id', $network->id)->where('key', 'inspection')->exists());

        app(ApplyIndustryTemplate::class)->handle($network, 'veterinary');
        $this->assertTrue(CustomFieldDefinition::where('network_id', $network->id)->where('key', 'species')->exists());
        $vaccine = HistoryEntryType::query()->where('network_id', $network->id)->where('key', 'vaccine')->first();
        $this->assertNotNull($vaccine);
        $this->assertSame('product', $vaccine->field_schema[0]['key'] ?? null);
        $this->assertTrue(
            Terminology::query()
                ->where('network_id', $network->id)
                ->where('entity_key', 'history')
                ->where('label', 'Historia clínica')
                ->exists()
        );
    }

    public function test_sync_history_catalog_updates_types_without_touching_workflow(): void
    {
        $network = Network::create([
            'name' => 'Test Sync History',
            'slug' => 'test-sync-history',
            'industry_template_key' => 'veterinary',
            'is_active' => true,
        ]);

        app(ApplyIndustryTemplate::class)->handle($network, 'veterinary');

        Terminology::query()
            ->where('network_id', $network->id)
            ->where('entity_key', 'history')
            ->update(['label' => 'Historial viejo']);

        HistoryEntryType::query()
            ->where('network_id', $network->id)
            ->where('key', 'consultation')
            ->update(['label' => 'Consulta vieja']);

        $workflowCount = WorkflowTemplate::query()->where('network_id', $network->id)->count();
        $actorCount = ActorType::query()->where('network_id', $network->id)->count();

        app(\App\Actions\Templates\SyncNetworkHistoryCatalog::class)->handle($network->fresh());

        $this->assertTrue(
            Terminology::query()
                ->where('network_id', $network->id)
                ->where('entity_key', 'history')
                ->where('label', 'Historia clínica')
                ->exists()
        );
        $this->assertSame(
            'Consulta',
            HistoryEntryType::query()
                ->where('network_id', $network->id)
                ->where('key', 'consultation')
                ->value('label')
        );
        $this->assertSame($workflowCount, WorkflowTemplate::query()->where('network_id', $network->id)->count());
        $this->assertSame($actorCount, ActorType::query()->where('network_id', $network->id)->count());
    }

    public function test_templates_seed_expected_ux_labels(): void
    {
        $network = Network::create([
            'name' => 'Test UX Labels',
            'slug' => 'test-ux-labels',
            'industry_template_key' => 'generic',
            'is_active' => true,
        ]);

        app(ApplyIndustryTemplate::class)->handle($network, 'veterinary');

        $this->assertTrue(
            Terminology::where('network_id', $network->id)
                ->where('entity_key', 'ux.status_attended')
                ->where('label', 'Atendido')
                ->exists()
        );

        app(ApplyIndustryTemplate::class)->handle($network, 'grooming');

        $this->assertTrue(
            Terminology::where('network_id', $network->id)
                ->where('entity_key', 'ux.status_attended')
                ->where('label', 'Entregado')
                ->exists()
        );
        $this->assertTrue(
            Terminology::where('network_id', $network->id)
                ->where('entity_key', 'ux.case_diagnosis')
                ->where('label', 'Observaciones del servicio')
                ->exists()
        );
    }

    public function test_export_round_trip_reproduces_veterinary_configuration(): void
    {
        $source = Network::create([
            'name' => 'Origen Vet',
            'slug' => 'origen-vet',
            'industry_template_key' => 'veterinary',
            'is_active' => true,
        ]);
        app(ApplyIndustryTemplate::class)->handle($source, 'veterinary');

        $directory = storage_path('framework/testing/industry-packs-'.uniqid());
        File::ensureDirectoryExists($directory);
        $output = $directory.DIRECTORY_SEPARATOR.'roundtrip-vet.json';

        $this->artisan('surc:export-template', [
            'network' => $source->slug,
            '--key' => 'roundtrip-vet',
            '--name' => 'Veterinaria clon',
            '--output' => $output,
        ])->assertSuccessful();

        $this->assertFileExists($output);

        config(['surc.industry_packs_path' => $directory]);
        app(IndustryTemplateRegistry::class)->flush();

        $target = Network::create([
            'name' => 'Destino Vet',
            'slug' => 'destino-vet',
            'industry_template_key' => 'generic',
            'is_active' => true,
        ]);
        app(ApplyIndustryTemplate::class)->handle($target, 'roundtrip-vet');

        $this->assertSame(
            Terminology::query()->where('network_id', $source->id)->orderBy('entity_key')->pluck('label', 'entity_key')->all(),
            Terminology::query()->where('network_id', $target->id)->orderBy('entity_key')->pluck('label', 'entity_key')->all(),
        );
        $this->assertSame(
            ActorType::query()->where('network_id', $source->id)->orderBy('sort_order')->pluck('key')->all(),
            ActorType::query()->where('network_id', $target->id)->orderBy('sort_order')->pluck('key')->all(),
        );
        $this->assertSame(
            WorkflowStage::query()->whereHas('template', fn ($query) => $query->where('network_id', $source->id))->orderBy('sort_order')->pluck('key')->all(),
            WorkflowStage::query()->whereHas('template', fn ($query) => $query->where('network_id', $target->id))->orderBy('sort_order')->pluck('key')->all(),
        );
        $this->assertSame(
            CustomFieldDefinition::query()->where('network_id', $source->id)->orderBy('sort_order')->pluck('key')->all(),
            CustomFieldDefinition::query()->where('network_id', $target->id)->orderBy('sort_order')->pluck('key')->all(),
        );
        $this->assertSame(
            HistoryEntryType::query()->where('network_id', $source->id)->orderBy('sort_order')->pluck('key')->all(),
            HistoryEntryType::query()->where('network_id', $target->id)->orderBy('sort_order')->pluck('key')->all(),
        );
        $this->assertEquals(
            NetworkSettings::agendaDefaults($source->fresh()),
            NetworkSettings::agendaDefaults($target->fresh()),
        );

        File::deleteDirectory($directory);
        config(['surc.industry_packs_path' => database_path('industry-packs')]);
        app(IndustryTemplateRegistry::class)->flush();
    }
}
