<?php

namespace Tests\Feature;

use App\Actions\Templates\ApplyIndustryTemplate;
use App\Models\HistoryEntryType;
use App\Models\Terminology;
use App\Support\Help\HelpContentResolver;
use App\Support\Tenancy\NetworkContext;
use Database\Seeders\HelpArticleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class HelpContentResolverTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        NetworkContext::clear();
        parent::tearDown();
    }

    public function test_resolver_uses_neutral_fallbacks_without_network(): void
    {
        NetworkContext::clear();

        $this->assertSame('el sujeto', HelpContentResolver::resolve('el {{subject}}'));
        $this->assertSame('los tipos configurados en la red', HelpContentResolver::resolve('{{history_types}}'));
        $this->assertSame('{{unknown}}', HelpContentResolver::resolve('{{unknown}}'));
    }

    public function test_resolver_uses_veterinary_labels_and_history_types(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-help-vet');
        app(ApplyIndustryTemplate::class)->handle($context['network'], 'veterinary');
        NetworkContext::set($context['network']->fresh());

        $html = HelpContentResolver::resolve('Operativa → {{subject_plural}}. Pestaña {{history}}. Tipos: {{history_types}}.');

        $this->assertStringContainsString('Animales', $html);
        $this->assertStringContainsString('Historia clínica', $html);
        $this->assertStringContainsString('Consulta, Control, Vacuna, Estudio', $html);
        $this->assertStringNotContainsString('{{subject}}', $html);
    }

    public function test_resolver_escapes_terminology_values(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-help-xss');
        Terminology::query()->create([
            'network_id' => $context['network']->id,
            'entity_key' => 'subject',
            'label' => '<script>alert(1)</script>',
            'label_plural' => 'X',
        ]);
        NetworkContext::set($context['network']);

        $html = HelpContentResolver::resolve('ver {{subject}}');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_plural_falls_back_to_singular_when_blank(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-help-plural');
        Terminology::query()->create([
            'network_id' => $context['network']->id,
            'entity_key' => 'subject',
            'label' => 'Equipo',
            'label_plural' => null,
        ]);
        NetworkContext::set($context['network']);

        $this->assertSame('Equipo', terminology_plural('subject', 'sujetos'));
    }

    public function test_seeder_source_does_not_hardcode_veterinary_labels(): void
    {
        $source = file_get_contents(database_path('seeders/HelpArticleSeeder.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('Historia clínica', $source);
        $this->assertStringNotContainsString('Vacuna', $source);
        $this->assertStringNotContainsString('Derivación', $source);
        $this->assertStringContainsString('{{history}}', $source);
        $this->assertStringContainsString('{{history_types}}', $source);
    }

    public function test_history_types_token_ignores_inactive_types(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-help-types');
        HistoryEntryType::query()->create([
            'network_id' => $context['network']->id,
            'key' => 'note',
            'label' => 'Nota activa',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        HistoryEntryType::query()->create([
            'network_id' => $context['network']->id,
            'key' => 'old',
            'label' => 'Tipo apagado',
            'sort_order' => 2,
            'is_active' => false,
        ]);
        NetworkContext::set($context['network']);

        $html = HelpContentResolver::resolve('{{history_types}}');

        $this->assertSame('Nota activa', $html);
        $this->assertStringNotContainsString('Tipo apagado', $html);
    }

    public function test_seeder_is_available_for_clinic_guides(): void
    {
        $this->seed(HelpArticleSeeder::class);

        $this->assertDatabaseHas('help_articles', ['slug' => 'guia-de-sede']);
        $this->assertDatabaseHas('help_articles', ['slug' => 'alta-de-sujetos']);
        $this->assertDatabaseHas('help_articles', ['slug' => 'roles-de-operacion']);
    }
}
