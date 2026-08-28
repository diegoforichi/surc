<?php

namespace Tests\Feature;

use App\Actions\Templates\ApplyIndustryTemplate;
use App\Filament\Pages\HelpCenter;
use App\Filament\Resources\HelpArticleResource;
use App\Models\HelpArticle;
use App\Models\User;
use Database\Seeders\HelpArticleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_training_and_legacy_tutorials(): void
    {
        $this->seedRoles();
        $this->createNetworkContext();

        $this->get('/admin/capacitacion')->assertRedirect();
        $this->get(route('public.tutorials', 'red-test'))->assertRedirect();
    }

    public function test_authenticated_user_is_redirected_from_legacy_tutorials_to_help_center(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-help@test.com');

        $this->actingAs($operator)
            ->get(route('public.tutorials', 'red-test'))
            ->assertRedirect(url('/admin/capacitacion'));
    }

    public function test_roles_only_see_matching_articles(): void
    {
        $this->seedRoles();
        $this->seed(HelpArticleSeeder::class);
        $context = $this->createNetworkContext();
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-art@test.com');
        $specialist = $this->createUserWithRole($context['network'], 'specialist', $context['organization'], 'esp-art@test.com');
        $owner = $this->createPlatformOwner();

        $this->actingAs($operator);
        Livewire::test(HelpCenter::class)
            ->assertSee('Registrar y confirmar una seña')
            ->assertSee('Ingreso, sede y privacidad')
            ->assertSee('Descargar guía PDF')
            ->assertDontSee('Tareas del dueño de plataforma')
            ->assertDontSee('Guía del especialista')
            ->assertDontSee('Sitio institucional de la red');

        $this->actingAs($specialist);
        Livewire::test(HelpCenter::class)
            ->assertSee('Guía del especialista')
            ->assertDontSee('Registrar y confirmar una seña')
            ->assertDontSee('Ingreso, sede y privacidad');

        $this->actingAs($owner);
        Livewire::test(HelpCenter::class)
            ->assertSee('Tareas del dueño de plataforma');
    }

    public function test_only_platform_owner_manages_help_articles(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $admin = $this->createUserWithRole($context['network'], 'network_admin', null, 'na-help@test.com');
        $owner = $this->createPlatformOwner();

        $this->actingAs($admin);
        $this->assertFalse(HelpArticleResource::canViewAny());
        $this->assertFalse(HelpArticleResource::canCreate());

        $this->actingAs($owner);
        $this->assertTrue(HelpArticleResource::canViewAny());
        $this->assertTrue(HelpArticleResource::canCreate());
    }

    public function test_invalid_video_url_does_not_render_iframe(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-vid@test.com');

        HelpArticle::create([
            'title' => 'Video inválido',
            'slug' => 'video-invalido',
            'category' => HelpArticle::CATEGORY_VIDEOS,
            'body' => '<p>texto</p>',
            'video_url' => 'https://example.com/watch',
            'audience_roles' => [],
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($operator);
        Livewire::test(HelpCenter::class)
            ->assertSee('Video inválido')
            ->assertDontSee('iframe', false)
            ->assertDontSee('example.com');
    }

    public function test_valid_video_url_renders_controlled_iframe(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-vid2@test.com');

        HelpArticle::create([
            'title' => 'Video válido',
            'slug' => 'video-valido',
            'category' => HelpArticle::CATEGORY_VIDEOS,
            'body' => '<p>texto</p>',
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'audience_roles' => [],
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($operator);
        Livewire::test(HelpCenter::class)
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_unpublished_article_is_hidden_and_pdf_returns_not_found(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-help-draft');
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-draft@test.com');

        $article = HelpArticle::create([
            'title' => 'Borrador interno',
            'slug' => 'borrador-interno',
            'category' => HelpArticle::CATEGORY_OPERATION,
            'body' => '<p>secreto</p>',
            'audience_roles' => [],
            'is_published' => false,
            'published_at' => null,
        ]);

        $this->actingAs($operator);
        Livewire::test(HelpCenter::class)
            ->assertDontSee('Borrador interno');

        $this->get(route('help.articles.pdf', $article))->assertNotFound();
    }

    public function test_pdf_is_forbidden_for_roles_outside_audience(): void
    {
        $this->seedRoles();
        $this->seed(HelpArticleSeeder::class);
        $context = $this->createNetworkContext('red-help-pdf');
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-pdf@test.com');
        $specialist = $this->createUserWithRole($context['network'], 'specialist', $context['organization'], 'esp-pdf@test.com');

        $guide = HelpArticle::query()->where('slug', 'guia-de-sede')->firstOrFail();

        $this->actingAs($operator)
            ->get(route('help.articles.pdf', $guide))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($specialist)
            ->get(route('help.articles.pdf', $guide))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_help_pdf(): void
    {
        $this->seedRoles();
        $this->seed(HelpArticleSeeder::class);
        $guide = HelpArticle::query()->where('slug', 'primeros-pasos')->firstOrFail();

        $this->get(route('help.articles.pdf', $guide))->assertRedirect();
    }

    public function test_clinic_admin_sees_veterinary_labels_in_help_center(): void
    {
        $this->seedRoles();
        $this->seed(HelpArticleSeeder::class);
        $context = $this->createNetworkContext('red-help-labels');
        app(ApplyIndustryTemplate::class)->handle($context['network'], 'veterinary');
        $admin = $this->createUserWithRole($context['network'], 'organization_admin', $context['organization'], 'oa-help@test.com');

        $this->actingAs($admin);
        Livewire::test(HelpCenter::class)
            ->assertSee('Historia clínica de la sede')
            ->assertSee('Alta de Propietario y Animal')
            ->assertSee('Animales')
            ->call('selectArticle', HelpArticle::query()->where('slug', 'historial-longitudinal')->value('id'))
            ->assertSee('Consulta, Control, Vacuna, Estudio')
            ->assertDontSee('{{subject}}')
            ->assertDontSee('{{history_types}}');
    }

    public function test_selecting_another_role_article_does_not_show_it(): void
    {
        $this->seedRoles();
        $this->seed(HelpArticleSeeder::class);
        $context = $this->createNetworkContext('red-help-select');
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-sel@test.com');
        $ownerArticle = HelpArticle::query()->where('slug', 'dueno-plataforma')->firstOrFail();

        $this->actingAs($operator);
        Livewire::test(HelpCenter::class)
            ->call('selectArticle', $ownerArticle->id)
            ->assertDontSee('Tareas del dueño de plataforma')
            ->assertDontSee('publica los manuales globales');
    }

    public function test_public_site_does_not_publish_training_guides(): void
    {
        $this->seedRoles();
        $this->seed(HelpArticleSeeder::class);
        $context = $this->createNetworkContext('red-help-public');

        $this->get(route('public.help', $context['network']->slug))
            ->assertOk()
            ->assertDontSee('Descargar guía PDF')
            ->assertDontSee('Ingreso, sede y privacidad');
    }

    protected function createPlatformOwner(): User
    {
        $owner = new User;
        $owner->forceFill([
            'name' => 'Owner',
            'email' => 'owner-help@test.com',
            'password' => 'password',
            'is_platform_owner' => true,
            'is_active' => true,
        ])->save();
        $owner->assignRole('platform_owner');

        return $owner;
    }
}
