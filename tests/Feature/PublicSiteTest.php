<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicSiteController;
use App\Models\Organization;
use App\Models\Party;
use App\Models\PublicContent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    public function test_published_content_is_visible_and_drafts_are_not(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-publica');

        PublicContent::create([
            'network_id' => $context['network']->id,
            'type' => 'blog',
            'title' => 'Nota publicada',
            'slug' => 'nota-publicada',
            'body' => '<p>Hola <strong>mundo</strong></p>',
            'is_published' => true,
            'published_at' => now(),
        ]);
        PublicContent::create([
            'network_id' => $context['network']->id,
            'type' => 'blog',
            'title' => 'Borrador secreto',
            'slug' => 'borrador',
            'body' => '<p>no</p>',
            'is_published' => false,
        ]);
        PublicContent::create([
            'network_id' => $context['network']->id,
            'type' => 'page',
            'title' => 'Ayuda de la red',
            'slug' => 'ayuda',
            'body' => '<p>Cómo operar</p>',
            'is_published' => true,
        ]);

        $this->get(route('public.home', 'red-publica'))
            ->assertOk()
            ->assertSee('Nota publicada')
            ->assertDontSee('Borrador secreto')
            ->assertSee($context['organization']->name);

        $this->get(route('public.post', ['red-publica', 'nota-publicada']))
            ->assertOk()
            ->assertSee('mundo', false);

        $this->get(route('public.help', 'red-publica'))
            ->assertOk()
            ->assertSee('Ayuda de la red');

        $this->get(route('public.organizations', 'red-publica'))
            ->assertOk()
            ->assertSee($context['organization']->name);

        $this->get(route('public.posts', 'red-publica'))
            ->assertOk()
            ->assertSee('Nota publicada')
            ->assertDontSee('Borrador secreto');
    }

    public function test_slug_is_unique_per_network_and_type(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-slug');

        PublicContent::create([
            'network_id' => $context['network']->id,
            'type' => 'blog',
            'title' => 'Uno',
            'slug' => 'misma',
            'body' => '<p>a</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);

        PublicContent::create([
            'network_id' => $context['network']->id,
            'type' => 'page',
            'title' => 'Página misma slug',
            'slug' => 'misma',
            'body' => '<p>b</p>',
            'is_published' => true,
        ]);

        $this->expectException(QueryException::class);

        PublicContent::create([
            'network_id' => $context['network']->id,
            'type' => 'blog',
            'title' => 'Duplicado',
            'slug' => 'misma',
            'body' => '<p>c</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function test_carousel_items_can_omit_slug(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-car');

        PublicContent::create([
            'network_id' => $context['network']->id,
            'type' => 'carousel',
            'title' => 'Banner uno',
            'slug' => '',
            'is_published' => true,
        ]);
        PublicContent::create([
            'network_id' => $context['network']->id,
            'type' => 'carousel',
            'title' => 'Banner dos',
            'slug' => null,
            'is_published' => true,
        ]);

        $this->assertSame(2, PublicContent::query()->where('type', 'carousel')->count());
    }

    public function test_specialists_index_lists_directory_profiles(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-esp');

        Party::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'actor_type_id' => $context['specialistType']->id,
            'display_name' => 'Dra. Visible',
            'is_active' => true,
        ]);

        $this->get(route('public.specialists', 'red-esp'))
            ->assertOk()
            ->assertSee('Dra. Visible');
    }

    public function test_another_network_cannot_see_foreign_content(): void
    {
        $this->seedRoles();
        $this->createNetworkContext('red-a');
        $b = $this->createNetworkContext('red-b');

        PublicContent::create([
            'network_id' => $b['network']->id,
            'type' => 'blog',
            'title' => 'Solo B',
            'slug' => 'solo-b',
            'body' => '<p>privado</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->get(route('public.home', 'red-a'))->assertOk()->assertDontSee('Solo B');
        $this->get(route('public.post', ['red-a', 'solo-b']))->assertNotFound();
        $this->get(route('public.posts', 'red-a'))->assertOk()->assertDontSee('Solo B');
    }

    public function test_home_limits_featured_items_and_links_to_indexes(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-limite');

        for ($i = 2; $i <= 4; $i++) {
            Organization::create([
                'network_id' => $context['network']->id,
                'name' => 'Sede Extra '.$i,
                'slug' => 'sede-extra-'.$i,
                'is_active' => true,
                'show_in_directory' => true,
            ]);
        }

        $this->get(route('public.home', 'red-limite'))
            ->assertOk()
            ->assertSee('Ver todas')
            ->assertSee(route('public.organizations', 'red-limite'), false);

        $this->assertLessThanOrEqual(
            PublicSiteController::HOME_LIMIT,
            substr_count($this->get(route('public.home', 'red-limite'))->getContent(), 'Sede Extra'),
        );

        $this->get(route('public.organizations', 'red-limite'))
            ->assertOk()
            ->assertSee('Sede Extra 2')
            ->assertSee('Sede Extra 4');
    }

    public function test_home_uses_network_profile_and_never_falls_back_to_clinic_whatsapp(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-wa');
        $context['organization']->update(['whatsapp' => '5491112345678']);
        $context['network']->update([
            'slogan' => 'Clínicas trabajando en red.',
            'description' => 'Descripción institucional de prueba.',
            'phone' => '43621234',
            'email' => 'red@example.com',
            'address' => 'Durazno',
        ]);

        $this->get(route('public.home', 'red-wa'))
            ->assertOk()
            ->assertSee('Clínicas trabajando en red.')
            ->assertSee('Descripción institucional de prueba.')
            ->assertSee('43621234')
            ->assertSee('red@example.com')
            ->assertSee('name="description"', false)
            ->assertSee('Clínicas trabajando en red.', false)
            ->assertDontSee('wa.me/5491112345678', false)
            ->assertDontSee('Sin foto')
            ->assertSee('Todavía no hay perfiles publicados')
            ->assertSee('Todavía no hay publicaciones')
            ->assertSee('Todavía no hay páginas de ayuda');

        $context['network']->update(['whatsapp' => '59899111222']);

        $this->get(route('public.home', 'red-wa'))
            ->assertOk()
            ->assertSee('wa.me/59899111222', false)
            ->assertDontSee('wa.me/5491112345678', false);

        $this->get(route('public.organization', ['red-wa', $context['organization']->slug]))
            ->assertOk()
            ->assertSee('wa.me/5491112345678', false);
    }

    public function test_home_shows_cover_photo_separate_from_logo(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-cover');
        $context['network']->update([
            'logo_path' => 'network-logos/marca.png',
            'cover_path' => 'network-covers/equipo.jpg',
        ]);

        $html = $this->get(route('public.home', 'red-cover'))
            ->assertOk()
            ->assertSee('network-logos/marca.png', false)
            ->assertSee('network-covers/equipo.jpg', false)
            ->assertDontSee('Sin foto')
            ->getContent();

        $this->assertTrue(str_contains($html, 'network-cover'));
        $this->assertTrue(str_contains($html, 'class="network-hero"'));
        $this->assertFalse(str_contains($html, 'class="network-hero network-hero--text"'));
    }

    public function test_organization_page_shows_website_when_loaded(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-web');
        $context['organization']->update(['website' => 'https://clinica.example.com']);

        $this->get(route('public.organization', ['red-web', $context['organization']->slug]))
            ->assertOk()
            ->assertSee('https://clinica.example.com', false);
    }
}
