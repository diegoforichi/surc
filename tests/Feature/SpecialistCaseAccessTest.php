<?php

namespace Tests\Feature;

use App\Enums\AgendaStatus;
use App\Enums\CaseStatus;
use App\Models\Agenda;
use App\Models\ActorType;
use App\Models\CaseRecord;
use App\Models\Network;
use App\Models\Organization;
use App\Models\Party;
use App\Models\Subject;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Support\Cases\CaseOperationalAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpecialistCaseAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'cases.manage']);
        Permission::firstOrCreate(['name' => 'cases.operate']);
        Role::firstOrCreate(['name' => 'specialist'])->syncPermissions(['cases.operate']);
    }

    public function test_specialist_only_sees_cases_from_their_agendas(): void
    {
        [$network, $clinic, $workflow, $specialistType] = $this->seedBase();

        $specialistUser = User::create([
            'network_id' => $network->id,
            'name' => 'Especialista A',
            'email' => 'esp-a@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $specialistUser->assignRole('specialist');

        $otherUser = User::create([
            'network_id' => $network->id,
            'name' => 'Especialista B',
            'email' => 'esp-b@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $otherUser->assignRole('specialist');

        $myParty = Party::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'actor_type_id' => $specialistType->id,
            'user_id' => $specialistUser->id,
            'display_name' => 'Mi especialista',
            'is_active' => true,
        ]);

        $otherParty = Party::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'actor_type_id' => $specialistType->id,
            'user_id' => $otherUser->id,
            'display_name' => 'Otro especialista',
            'is_active' => true,
        ]);

        $myAgenda = Agenda::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'specialist_party_id' => $myParty->id,
            'scheduled_date' => now()->addDay(),
            'status' => AgendaStatus::Planned,
        ]);

        $otherAgenda = Agenda::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'specialist_party_id' => $otherParty->id,
            'scheduled_date' => now()->addDay(),
            'status' => AgendaStatus::Planned,
        ]);

        $subject = Subject::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'label_name' => 'Paciente',
            'is_active' => true,
        ]);

        $myCase = CaseRecord::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'workflow_template_id' => $workflow->id,
            'subject_id' => $subject->id,
            'agenda_id' => $myAgenda->id,
            'code' => 'MINE',
            'title' => 'Mi caso',
            'status' => CaseStatus::Open,
        ]);

        $otherCase = CaseRecord::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'workflow_template_id' => $workflow->id,
            'subject_id' => $subject->id,
            'agenda_id' => $otherAgenda->id,
            'code' => 'OTHER',
            'title' => 'Caso ajeno',
            'status' => CaseStatus::Open,
        ]);

        $this->actingAs($specialistUser);

        $visibleIds = CaseOperationalAccess::scopeCasesForUser(CaseRecord::query())->pluck('code')->all();

        $this->assertEquals(['MINE'], $visibleIds);
        $this->assertTrue(CaseOperationalAccess::canAccessCase($myCase));
        $this->assertFalse(CaseOperationalAccess::canAccessCase($otherCase));

        $this->get(route('cases.show', $myCase))->assertOk();
        $this->get(route('cases.show', $otherCase))->assertForbidden();
    }

    /**
     * @return array{0: Network, 1: Organization, 2: WorkflowTemplate, 3: ActorType}
     */
    protected function seedBase(): array
    {
        $network = Network::create([
            'name' => 'Red Test',
            'slug' => 'red-test',
            'industry_template_key' => 'generic',
            'is_active' => true,
        ]);

        $clinic = Organization::create([
            'network_id' => $network->id,
            'name' => 'Clínica Test',
            'slug' => 'clinica-test',
            'is_active' => true,
        ]);

        $workflow = WorkflowTemplate::create([
            'network_id' => $network->id,
            'name' => 'Flujo test',
            'is_default' => true,
            'is_active' => true,
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

        return [$network, $clinic, $workflow, $specialistType];
    }
}
