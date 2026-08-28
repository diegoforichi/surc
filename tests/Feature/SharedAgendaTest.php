<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Filament\Resources\AgendaResource;
use App\Filament\Resources\AgendaResource\RelationManagers\CasesRelationManager;
use App\Filament\Resources\CaseRecordResource;
use App\Filament\Resources\CaseRecordResource\Pages\CreateCaseRecord;
use App\Filament\Resources\SubjectResource;
use App\Livewire\CaseWorkspace;
use App\Models\Agenda;
use App\Models\CaseRecord;
use App\Models\Organization;
use App\Models\Party;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\WorkflowStage;
use App\Support\Cases\CaseOperationalAccess;
use App\Support\History\HistoryAccess;
use App\Support\Settings\NetworkSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class SharedAgendaTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    public function test_origin_clinic_sees_shared_agenda_but_not_a_private_one(): void
    {
        $setup = $this->twoClinics();

        $this->actingAs($setup['originAdmin']);

        $visible = CaseOperationalAccess::scopeAgendasForUser(Agenda::query())->pluck('id');

        $this->assertTrue($visible->contains($setup['sharedAgenda']->id));
        $this->assertFalse($visible->contains($setup['privateAgenda']->id));
        $this->assertFalse(AgendaResource::canEdit($setup['sharedAgenda']));
        $this->assertTrue(CaseOperationalAccess::canManageAgenda($setup['originAgenda']));
    }

    public function test_assigning_to_shared_agenda_keeps_origin_organization(): void
    {
        $setup = $this->twoClinics();

        $this->actingAs($setup['originAdmin']);

        Livewire::test(CreateCaseRecord::class)
            ->fillForm([
                'organization_id' => $setup['host']->id,
                'subject_id' => $setup['originSubject']->id,
                'workflow_template_id' => $setup['context']['workflow']->id,
                'title' => 'Derivación a la anfitriona',
                'status' => CaseStatus::Open->value,
                'agenda_id' => $setup['sharedAgenda']->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cases', [
            'title' => 'Derivación a la anfitriona',
            'organization_id' => $setup['origin']->id,
            'agenda_id' => $setup['sharedAgenda']->id,
            'subject_id' => $setup['originSubject']->id,
        ]);
        $this->assertDatabaseMissing('cases', [
            'title' => 'Derivación a la anfitriona',
            'organization_id' => $setup['host']->id,
        ]);
    }

    public function test_host_operates_origin_case_on_shared_agenda_without_reading_history(): void
    {
        $setup = $this->twoClinics(withHistory: true);

        $stage = WorkflowStage::create([
            'workflow_template_id' => $setup['context']['workflow']->id,
            'key' => 'pre_intent',
            'label' => 'Preagenda',
            'sort_order' => 0,
            'is_terminal' => false,
        ]);

        $case = $this->createCase(array_merge($setup['context'], [
            'organization' => $setup['origin'],
        ]), [
            'organization_id' => $setup['origin']->id,
            'subject_id' => $setup['originSubject']->id,
            'agenda_id' => $setup['sharedAgenda']->id,
            'code' => 'DER-ORIGEN',
            'current_stage_id' => $stage->id,
        ]);

        $this->actingAs($setup['hostAdmin']);

        $this->assertTrue(
            CaseOperationalAccess::scopeCasesForUser(CaseRecord::query())->whereKey($case->id)->exists()
        );
        $this->assertTrue(CaseOperationalAccess::canAccessCase($case));
        $this->assertFalse(CaseOperationalAccess::canManageCase($case));
        $this->assertFalse(HistoryAccess::canViewSubject($setup['hostAdmin'], $setup['originSubject']));
        $this->assertFalse(HistoryAccess::canShareCase($setup['hostAdmin'], $case));
        $this->assertFalse(
            SubjectResource::getEloquentQuery()->whereKey($setup['originSubject']->id)->exists()
        );

        $this->get(route('cases.show', $case))->assertOk();
        $this->get(route('cases.ticket', $case))
            ->assertOk()
            ->assertSee('Se atiende en:')
            ->assertSee($setup['host']->name);
        $this->get(route('history.subjects.pdf', $setup['originSubject']))->assertForbidden();

        $payment = Payment::create([
            'network_id' => $setup['context']['network']->id,
            'case_id' => $case->id,
            'type' => 'deposit',
            'amount' => 200,
            'status' => 'pending',
            'method' => 'efectivo',
        ]);

        Livewire::test(CaseWorkspace::class, ['case' => $case])
            ->call('confirmPayment', $payment->id);

        $this->assertSame('confirmed', $payment->fresh()->status);
    }

    public function test_private_agenda_keeps_cases_isolated(): void
    {
        $setup = $this->twoClinics();

        $case = $this->createCase(array_merge($setup['context'], [
            'organization' => $setup['origin'],
        ]), [
            'organization_id' => $setup['origin']->id,
            'subject_id' => $setup['originSubject']->id,
            'agenda_id' => $setup['originAgenda']->id,
            'code' => 'DER-PRIV',
        ]);

        $this->actingAs($setup['hostAdmin']);

        $this->assertFalse(
            CaseOperationalAccess::scopeCasesForUser(CaseRecord::query())->whereKey($case->id)->exists()
        );
        $this->assertFalse(CaseOperationalAccess::canAccessCase($case));
        $this->get(route('cases.show', $case))->assertForbidden();
        $this->get(route('cases.ticket', $case))->assertForbidden();
    }

    public function test_host_cannot_assign_another_clinic_unassigned_case_from_agenda(): void
    {
        $setup = $this->twoClinics();

        $foreign = $this->createCase(array_merge($setup['context'], [
            'organization' => $setup['origin'],
        ]), [
            'organization_id' => $setup['origin']->id,
            'subject_id' => $setup['originSubject']->id,
            'code' => 'DER-AJENO',
        ]);

        $this->actingAs($setup['hostAdmin']);

        $options = CasesRelationManager::unassignedCaseOptions();

        $this->assertArrayNotHasKey($foreign->id, $options);
        $this->assertFalse(CaseRecordResource::canEdit($foreign));
    }

    public function test_shared_agenda_option_label_mentions_network_opening(): void
    {
        $setup = $this->twoClinics();

        $this->assertStringContainsString(
            'abierta a la red',
            $setup['sharedAgenda']->fresh(['specialist', 'organization'])->optionLabel(),
        );
        $this->assertStringNotContainsString(
            'abierta a la red',
            $setup['privateAgenda']->fresh(['specialist', 'organization'])->optionLabel(),
        );
    }

    /**
     * @return array{
     *     context: array<string, mixed>,
     *     host: Organization,
     *     origin: Organization,
     *     hostAdmin: \App\Models\User,
     *     originAdmin: \App\Models\User,
     *     originSubject: Subject,
     *     sharedAgenda: Agenda,
     *     privateAgenda: Agenda,
     *     originAgenda: Agenda
     * }
     */
    protected function twoClinics(bool $withHistory = false): array
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();

        if ($withHistory) {
            $context['network']->update([
                'settings' => array_replace_recursive(NetworkSettings::defaults(), [
                    'modules' => ['history_enabled' => true],
                ]),
            ]);
            $context['organization']->update(['history_enabled' => true]);
        }

        $host = $context['organization'];
        $origin = $host->replicate();
        $origin->slug = 'sede-origen';
        $origin->name = 'Sede Origen';
        $origin->history_enabled = $withHistory;
        $origin->save();

        $hostAdmin = $this->createUserWithRole($context['network'], 'organization_admin', $host, 'host@test.com');
        $originAdmin = $this->createUserWithRole($context['network'], 'organization_admin', $origin, 'origin@test.com');

        $specialist = Party::create([
            'network_id' => $context['network']->id,
            'organization_id' => $host->id,
            'actor_type_id' => $context['specialistType']->id,
            'display_name' => 'Especialista red',
            'is_active' => true,
        ]);

        $sharedAgenda = $this->createAgenda($context, $specialist, [
            'title' => 'Visita abierta',
            'is_shared' => true,
        ]);
        $privateAgenda = $this->createAgenda($context, $specialist, [
            'title' => 'Visita interna',
            'is_shared' => false,
        ]);
        $originAgenda = $this->createAgenda($context, $specialist, [
            'organization_id' => $origin->id,
            'title' => 'Agenda origen',
        ]);

        $originSubject = Subject::create([
            'network_id' => $context['network']->id,
            'organization_id' => $origin->id,
            'label_name' => 'Paciente origen',
            'is_active' => true,
        ]);

        return compact(
            'context',
            'host',
            'origin',
            'hostAdmin',
            'originAdmin',
            'originSubject',
            'sharedAgenda',
            'privateAgenda',
            'originAgenda',
        );
    }
}
