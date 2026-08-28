<?php

namespace Tests\Feature;

use App\Models\Network;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurcNewInstanceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_creates_network_admin_and_organization_without_extra_users(): void
    {
        $this->artisan('surc:new-instance', [
            '--name' => 'Red Taller',
            '--slug' => 'red-taller',
            '--template' => 'generic',
            '--admin-name' => 'Ana Dueña',
            '--admin-email' => 'ana@taller.test',
            '--admin-password' => 'clave-segura',
            '--org' => 'Sede Centro',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $network = Network::query()->where('slug', 'red-taller')->first();
        $this->assertNotNull($network);
        $this->assertSame('generic', $network->industry_template_key);

        $this->assertSame(1, User::query()->count());
        $admin = User::query()->where('email', 'ana@taller.test')->first();
        $this->assertNotNull($admin);
        $this->assertSame($network->id, $admin->network_id);
        $this->assertTrue($admin->hasRole('network_admin'));
        $this->assertFalse($admin->is_platform_owner);

        $organization = Organization::query()->where('network_id', $network->id)->first();
        $this->assertNotNull($organization);
        $this->assertSame('Sede Centro', $organization->name);

        $this->assertDatabaseMissing('users', ['email' => 'owner@surc.test']);
    }

    public function test_fails_without_password_in_non_interactive_mode(): void
    {
        $this->artisan('surc:new-instance', [
            '--name' => 'Red Taller',
            '--slug' => 'red-taller-fail',
            '--template' => 'generic',
            '--admin-name' => 'Ana Dueña',
            '--admin-email' => 'ana@taller.test',
            '--no-interaction' => true,
        ])
            ->expectsOutput('La contraseña del admin de red es obligatoria.')
            ->assertFailed();

        $this->assertDatabaseMissing('networks', ['slug' => 'red-taller-fail']);
        $this->assertSame(0, User::query()->count());
    }

    public function test_create_user_requires_password(): void
    {
        $this->artisan('surc:create-network', [
            'name' => 'Red Aux',
            'slug' => 'red-aux',
            'template' => 'generic',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->artisan('surc:create-user', [
            'email' => 'op@aux.test',
            '--role' => 'operator',
            '--network' => 'red-aux',
            '--name' => 'Operador',
            '--no-interaction' => true,
        ])
            ->expectsOutput('La contraseña es obligatoria.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'op@aux.test']);
    }
}
