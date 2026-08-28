<?php

namespace Tests\Feature;

use App\Models\CaseRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class AgendaOrderAndConstancyTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    public function test_assigning_cases_to_agenda_creates_stable_order(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $specialist = \App\Models\Party::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'actor_type_id' => $context['specialistType']->id,
            'display_name' => 'Esp',
            'is_active' => true,
        ]);
        $agenda = $this->createAgenda($context, $specialist);

        $first = $this->createCase($context, ['agenda_id' => $agenda->id, 'code' => 'C-1']);
        $second = $this->createCase($context, ['agenda_id' => $agenda->id, 'code' => 'C-2']);

        $this->assertSame(1, $first->fresh()->agenda_order);
        $this->assertSame(2, $second->fresh()->agenda_order);

        $first->update(['title' => 'Renombrado']);
        $this->assertSame(1, $first->fresh()->agenda_order);
    }

    public function test_constancy_uses_agenda_override_and_is_authorized(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $specialist = \App\Models\Party::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'actor_type_id' => $context['specialistType']->id,
            'display_name' => 'Dra. Test',
            'is_active' => true,
        ]);
        $agenda = $this->createAgenda($context, $specialist);
        $agenda->update(['instructions' => 'Traer radiografía.']);

        $case = $this->createCase($context, ['agenda_id' => $agenda->id, 'code' => 'C-CONST']);
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-const@test.com');

        $response = $this->actingAs($operator)->get(route('cases.ticket', $case));
        $response->assertOk();
        $response->assertSee('Traer radiografía.');
        $response->assertSee('Acepto las indicaciones.');
        $response->assertSee('Horario a confirmar');
        $response->assertSee('Dra. Test');
        $this->assertTrue($case->events()->where('type', 'constancy_printed')->exists());
    }
}
