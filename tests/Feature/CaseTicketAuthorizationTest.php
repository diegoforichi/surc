<?php

namespace Tests\Feature;

use App\Enums\AgendaStatus;
use App\Enums\CaseStatus;
use App\Models\Agenda;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class CaseTicketAuthorizationTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    public function test_user_without_permission_cannot_download_report(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $case = $this->createCase($context);

        $user = User::create([
            'network_id' => $context['network']->id,
            'name' => 'Sin permiso',
            'email' => 'sin-permiso@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('cases.report', $case))->assertForbidden();
        $this->actingAs($user)->get(route('cases.ticket', $case))->assertForbidden();
    }

    public function test_operator_of_another_organization_cannot_access_ticket(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $case = $this->createCase($context);

        $otherOrg = $context['organization']->replicate();
        $otherOrg->slug = 'otra-sede';
        $otherOrg->name = 'Otra sede';
        $otherOrg->save();

        $operator = $this->createUserWithRole($context['network'], 'operator', $otherOrg, 'op-otra@test.com');

        $this->actingAs($operator)->get(route('cases.ticket', $case))->assertForbidden();
    }

    public function test_specialist_cannot_access_another_specialist_case_ticket(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();

        $mine = $this->createUserWithRole($context['network'], 'specialist', $context['organization'], 'esp-a@test.com');
        $other = $this->createUserWithRole($context['network'], 'specialist', $context['organization'], 'esp-b@test.com');

        $myParty = Party::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'actor_type_id' => $context['specialistType']->id,
            'user_id' => $mine->id,
            'display_name' => 'Mio',
            'is_active' => true,
        ]);
        $otherParty = Party::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'actor_type_id' => $context['specialistType']->id,
            'user_id' => $other->id,
            'display_name' => 'Ajeno',
            'is_active' => true,
        ]);

        $agenda = Agenda::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'specialist_party_id' => $otherParty->id,
            'scheduled_date' => now()->addDay(),
            'status' => AgendaStatus::Planned,
        ]);

        $case = $this->createCase($context, [
            'agenda_id' => $agenda->id,
            'status' => CaseStatus::Open,
        ]);

        $this->actingAs($mine)->get(route('cases.ticket', $case))->assertForbidden();
        $this->actingAs($other)->get(route('cases.ticket', $case))->assertOk();
    }

    public function test_operator_of_same_organization_can_download_report(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $case = $this->createCase($context);
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op@test.com');

        $response = $this->actingAs($operator)->get(route('cases.report', $case));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }
}
