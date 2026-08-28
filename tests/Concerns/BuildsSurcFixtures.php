<?php

namespace Tests\Concerns;

use App\Enums\AgendaStatus;
use App\Enums\CaseStatus;
use App\Models\ActorType;
use App\Models\Agenda;
use App\Models\CaseRecord;
use App\Models\Network;
use App\Models\Organization;
use App\Models\Party;
use App\Models\Subject;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

trait BuildsSurcFixtures
{
    protected function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * @return array{network: Network, organization: Organization, workflow: WorkflowTemplate, specialistType: ActorType}
     */
    protected function createNetworkContext(string $slug = 'red-test'): array
    {
        $network = Network::create([
            'name' => 'Red Test',
            'slug' => $slug,
            'industry_template_key' => 'generic',
            'is_active' => true,
        ]);

        $organization = Organization::create([
            'network_id' => $network->id,
            'name' => 'Sede Test',
            'slug' => 'sede-'.$slug,
            'is_active' => true,
            'show_in_directory' => true,
        ]);

        $workflow = WorkflowTemplate::create([
            'network_id' => $network->id,
            'name' => 'Flujo test',
            'is_default' => true,
            'is_active' => true,
            'instructions' => 'Ayuno de 8 horas.',
            'consent_text' => 'Acepto las indicaciones.',
        ]);

        $specialistType = ActorType::create([
            'network_id' => $network->id,
            'key' => 'specialist',
            'label' => 'Especialista',
            'category' => 'specialist',
            'is_user_linkable' => true,
            'show_in_directory' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return compact('network', 'organization', 'workflow', 'specialistType');
    }

    protected function createUserWithRole(Network $network, string $role, ?Organization $organization = null, string $email = 'user@test.com'): User
    {
        $user = User::create([
            'network_id' => $network->id,
            'organization_id' => $organization?->id,
            'name' => $role,
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function createCase(array $context, array $overrides = []): CaseRecord
    {
        $subject = Subject::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'label_name' => 'Paciente',
            'is_active' => true,
        ]);

        return CaseRecord::create(array_merge([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'workflow_template_id' => $context['workflow']->id,
            'subject_id' => $subject->id,
            'code' => 'CASE-'.uniqid(),
            'title' => 'Caso test',
            'status' => CaseStatus::Open,
        ], $overrides));
    }

    protected function createAgenda(array $context, Party $specialist, array $overrides = []): Agenda
    {
        return Agenda::create(array_merge([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'specialist_party_id' => $specialist->id,
            'title' => 'Visita test',
            'scheduled_date' => now()->addDay(),
            'status' => AgendaStatus::Planned,
        ], $overrides));
    }
}
