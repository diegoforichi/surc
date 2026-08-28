<?php

namespace Tests\Feature;

use App\Enums\RequirementType;
use App\Filament\Resources\OrganizationResource;
use App\Filament\Resources\PublicContentResource;
use App\Filament\Resources\UserResource;
use App\Livewire\CaseWorkspace;
use App\Models\Payment;
use App\Models\StageRequirement;
use App\Models\WorkflowStage;
use App\Support\Auth\AssignableRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class PrivilegeIsolationTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    public function test_organization_admin_cannot_assign_platform_or_network_roles(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $admin = $this->createUserWithRole($context['network'], 'organization_admin', $context['organization'], 'oa@test.com');

        $this->actingAs($admin);

        $this->assertSame(['operator', 'specialist'], AssignableRoles::names($admin));
        $this->assertFalse(UserResource::canEdit($this->createPlatformOwner()));
    }

    public function test_operator_cannot_view_organizations_resource(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op@test.com');

        $this->actingAs($operator);

        $this->assertFalse(OrganizationResource::canViewAny());
        $this->assertFalse(OrganizationResource::canCreate());
    }

    public function test_organization_admin_can_edit_own_organization_only(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $admin = $this->createUserWithRole($context['network'], 'organization_admin', $context['organization'], 'oa@test.com');

        $other = $context['organization']->replicate();
        $other->slug = 'otra';
        $other->name = 'Otra';
        $other->save();

        $this->actingAs($admin);

        $this->assertTrue(OrganizationResource::canViewAny());
        $this->assertTrue(OrganizationResource::canEdit($context['organization']));
        $this->assertFalse(OrganizationResource::canEdit($other));
        $this->assertFalse(OrganizationResource::canCreate());
    }

    public function test_operator_can_confirm_payments_but_specialist_cannot(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-pay@test.com');
        $specialist = $this->createUserWithRole($context['network'], 'specialist', $context['organization'], 'esp-pay@test.com');

        $this->assertTrue($operator->can('payments.confirm'));
        $this->assertFalse($specialist->can('payments.confirm'));
        $this->assertTrue($this->createUserWithRole($context['network'], 'organization_admin', $context['organization'], 'oa-pay@test.com')->can('payments.confirm'));

        $stage = WorkflowStage::create([
            'workflow_template_id' => $context['workflow']->id,
            'key' => 'pre_intent',
            'label' => 'Preagenda',
            'sort_order' => 0,
            'is_terminal' => false,
        ]);

        StageRequirement::create([
            'workflow_stage_id' => $stage->id,
            'key' => 'payment_confirmed',
            'label' => 'Pago confirmado',
            'type' => RequirementType::Payment,
            'is_mandatory' => true,
            'sort_order' => 1,
        ]);

        $case = $this->createCase($context, [
            'current_stage_id' => $stage->id,
        ]);

        $payment = Payment::create([
            'network_id' => $context['network']->id,
            'case_id' => $case->id,
            'type' => 'deposit',
            'amount' => 500,
            'status' => 'pending',
            'method' => 'efectivo',
        ]);

        $this->actingAs($operator);
        Livewire::test(CaseWorkspace::class, ['case' => $case])
            ->call('confirmPayment', $payment->id);

        $this->assertSame('confirmed', $payment->fresh()->status);
    }

    public function test_only_public_managers_see_public_content_resource(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-pub@test.com');
        $admin = $this->createUserWithRole($context['network'], 'network_admin', null, 'na-pub@test.com');
        $specialist = $this->createUserWithRole($context['network'], 'specialist', $context['organization'], 'esp-pub@test.com');

        $this->actingAs($operator);
        $this->assertFalse(PublicContentResource::canViewAny());

        $this->actingAs($specialist);
        $this->assertFalse(PublicContentResource::canViewAny());

        $this->actingAs($admin);
        $this->assertTrue(PublicContentResource::canViewAny());
        $this->assertTrue(PublicContentResource::canCreate());
        $this->assertTrue(\App\Filament\Pages\InstitutionalProfile::canAccess());
    }

    public function test_operator_cannot_access_institutional_profile(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-inst@test.com');

        $this->actingAs($operator);
        $this->assertFalse(\App\Filament\Pages\InstitutionalProfile::canAccess());
    }

    public function test_network_admin_can_save_institutional_profile(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $admin = $this->createUserWithRole($context['network'], 'network_admin', null, 'na-inst@test.com');

        $this->actingAs($admin);

        Livewire::test(\App\Filament\Pages\InstitutionalProfile::class)
            ->fillForm([
                'slogan' => 'Slogan de red',
                'whatsapp' => '59899111000',
            ])
            ->call('save');

        $this->assertSame('Slogan de red', $context['network']->fresh()->slogan);
        $this->assertSame('59899111000', $context['network']->fresh()->whatsapp);
    }

    public function test_organization_admin_cannot_create_subject_in_another_clinic(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $admin = $this->createUserWithRole($context['network'], 'organization_admin', $context['organization'], 'oa-cross@test.com');

        $other = $context['organization']->replicate();
        $other->slug = 'otra-clinica';
        $other->name = 'Otra clínica';
        $other->save();

        $this->actingAs($admin);

        $forced = \App\Filament\Resources\SubjectResource::assignNetworkAndOrganization([
            'organization_id' => $other->id,
            'label_name' => 'Fido cruzado',
        ]);

        $this->assertSame($context['organization']->id, $forced['organization_id']);
        $this->assertSame($context['network']->id, $forced['network_id']);

        Livewire::test(\App\Filament\Resources\SubjectResource\Pages\CreateSubject::class)
            ->fillForm([
                'organization_id' => $other->id,
                'label_name' => 'Fido cruzado',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('subjects', [
            'label_name' => 'Fido cruzado',
            'organization_id' => $context['organization']->id,
            'network_id' => $context['network']->id,
        ]);
        $this->assertDatabaseMissing('subjects', [
            'label_name' => 'Fido cruzado',
            'organization_id' => $other->id,
        ]);
    }

    public function test_network_admin_can_assign_any_clinic_on_create(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $admin = $this->createUserWithRole($context['network'], 'network_admin', null, 'na-cross@test.com');

        $other = $context['organization']->replicate();
        $other->slug = 'otra-na';
        $other->name = 'Otra NA';
        $other->save();

        $this->actingAs($admin);

        $data = \App\Filament\Resources\SubjectResource::assignNetworkAndOrganization([
            'organization_id' => $other->id,
        ]);

        $this->assertSame($other->id, $data['organization_id']);
    }

    public function test_host_clinic_cannot_share_origin_history_on_shared_agenda(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $context['network']->update([
            'settings' => array_replace_recursive(\App\Support\Settings\NetworkSettings::defaults(), [
                'modules' => ['history_enabled' => true],
            ]),
        ]);
        $context['organization']->update(['history_enabled' => true]);

        $origin = $context['organization']->replicate();
        $origin->slug = 'origen-share';
        $origin->name = 'Origen share';
        $origin->history_enabled = true;
        $origin->save();

        $hostAdmin = $this->createUserWithRole($context['network'], 'organization_admin', $context['organization'], 'host-share@test.com');
        $originAdmin = $this->createUserWithRole($context['network'], 'organization_admin', $origin, 'origin-share@test.com');

        $specialist = \App\Models\Party::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'actor_type_id' => $context['specialistType']->id,
            'display_name' => 'Esp share',
            'is_active' => true,
        ]);
        $agenda = $this->createAgenda($context, $specialist, ['is_shared' => true]);

        $subject = \App\Models\Subject::create([
            'network_id' => $context['network']->id,
            'organization_id' => $origin->id,
            'label_name' => 'Paciente share',
            'is_active' => true,
        ]);
        $case = $this->createCase(array_merge($context, ['organization' => $origin]), [
            'organization_id' => $origin->id,
            'subject_id' => $subject->id,
            'agenda_id' => $agenda->id,
        ]);

        $this->assertFalse(\App\Support\History\HistoryAccess::canShareCase($hostAdmin, $case));
        $this->assertTrue(\App\Support\History\HistoryAccess::canShareCase($originAdmin, $case));
        $this->assertFalse(\App\Support\History\HistoryAccess::canViewSubject($hostAdmin, $subject));
    }

    protected function createPlatformOwner(): \App\Models\User
    {
        $owner = new \App\Models\User;
        $owner->forceFill([
            'name' => 'Owner',
            'email' => 'owner-priv@test.com',
            'password' => 'password',
            'is_platform_owner' => true,
            'is_active' => true,
        ])->save();

        return $owner;
    }
}
