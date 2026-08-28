<?php

namespace Tests\Feature;

use App\Actions\History\CreateHistoryAddendum;
use App\Actions\History\FinalizeHistoryEntry;
use App\Filament\Resources\SubjectResource;
use App\Filament\Resources\SubjectResource\Pages\EditSubject;
use App\Filament\Resources\SubjectResource\Pages\ViewSubject;
use App\Filament\Resources\SubjectResource\RelationManagers\HistoryEntriesRelationManager;
use App\Models\HistoryEntryType;
use App\Models\SubjectHistoryEntry;
use App\Support\History\HistoryAccess;
use App\Support\Settings\NetworkSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class SubjectInternalRecordTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    public function test_operator_can_view_subject_ficha_but_cannot_edit_it(): void
    {
        $this->seedRoles();
        $context = $this->enableHistory($this->createNetworkContext());
        $subject = $this->createCase($context)->subject;
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-ficha@test.com');

        $this->actingAs($operator);

        $this->assertTrue(SubjectResource::canViewAny());
        $this->assertTrue(SubjectResource::canView($subject));
        $this->assertFalse(SubjectResource::canCreate());
        $this->assertFalse(SubjectResource::canEdit($subject));
        $this->assertTrue(HistoryEntriesRelationManager::canViewForRecord($subject, ViewSubject::class));

        Livewire::test(ViewSubject::class, ['record' => $subject->getKey()])
            ->assertSuccessful();

        Livewire::test(EditSubject::class, ['record' => $subject->getKey()])
            ->assertForbidden();
    }

    public function test_operator_does_not_see_subjects_when_history_is_off(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-off@test.com');

        $this->actingAs($operator);
        $this->assertFalse(SubjectResource::canViewAny());
    }

    public function test_specialist_and_other_organization_cannot_access_ficha(): void
    {
        $this->seedRoles();
        $context = $this->enableHistory($this->createNetworkContext());
        $subject = $this->createCase($context)->subject;
        $specialist = $this->createUserWithRole($context['network'], 'specialist', $context['organization'], 'esp-ficha@test.com');

        $this->actingAs($specialist);
        $this->assertFalse(SubjectResource::canViewAny());
        $this->assertFalse(SubjectResource::canView($subject));
        $this->assertFalse(HistoryAccess::canViewSubject($specialist, $subject));

        $otherOrg = $context['organization']->replicate();
        $otherOrg->slug = 'otra-ficha';
        $otherOrg->history_enabled = true;
        $otherOrg->save();
        $otherOp = $this->createUserWithRole($context['network'], 'operator', $otherOrg, 'op-otra-ficha@test.com');

        $this->actingAs($otherOp);
        $this->assertFalse(SubjectResource::canView($subject));
        $this->assertFalse(HistoryAccess::canViewSubject($otherOp, $subject));
    }

    public function test_platform_owner_can_open_subject_but_not_history(): void
    {
        $this->seedRoles();
        $context = $this->enableHistory($this->createNetworkContext());
        $subject = $this->createCase($context)->subject;
        $owner = $this->createPlatformOwner();
        $owner->assignRole('platform_owner');

        $this->actingAs($owner);
        $this->assertTrue(SubjectResource::canView($subject));
        $this->assertFalse(HistoryAccess::canViewSubject($owner, $subject));
        $this->assertFalse(HistoryEntriesRelationManager::canViewForRecord($subject, ViewSubject::class));
    }

    public function test_network_admin_opens_subject_and_cases_but_not_history_tab(): void
    {
        $this->seedRoles();
        $context = $this->enableHistory($this->createNetworkContext());
        $subject = $this->createCase($context)->subject;
        $admin = $this->createUserWithRole($context['network'], 'network_admin', null, 'na-ficha@test.com');

        $this->actingAs($admin);
        $this->assertTrue(SubjectResource::canView($subject));
        $this->assertTrue(SubjectResource::canEdit($subject));
        $this->assertFalse(HistoryAccess::canViewSubject($admin, $subject));
        $this->assertFalse(HistoryEntriesRelationManager::canViewForRecord($subject, ViewSubject::class));
        $this->assertFalse(HistoryEntriesRelationManager::canViewForRecord($subject, EditSubject::class));
    }

    public function test_dynamic_payload_is_filtered_to_schema_and_final_stays_immutable(): void
    {
        $this->seedRoles();
        $context = $this->enableHistory($this->createNetworkContext());
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-payload@test.com');
        $subject = $this->createCase($context)->subject;
        $type = HistoryEntryType::create([
            'network_id' => $context['network']->id,
            'key' => 'inspection',
            'label' => 'Inspección',
            'is_active' => true,
            'field_schema' => [
                ['key' => 'mileage', 'label' => 'Kilometraje', 'type' => 'number'],
            ],
        ]);

        $this->assertSame('mileage', $type->fresh()->field_schema[0]['key']);

        $entry = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $subject->id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Revisión',
            'payload' => \App\Support\History\HistoryFieldSchema::extractPayload($type->field_schema, [
                'mileage' => 1500,
                'hack' => 'no',
            ]),
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);

        $this->assertSame(['mileage' => 1500], $entry->payload);

        app(FinalizeHistoryEntry::class)->handle($entry, $operator);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $entry->fresh()->update(['payload' => ['mileage' => 9]]);
    }

    public function test_history_attachments_are_authorized_per_organization(): void
    {
        Storage::fake('local');
        $this->seedRoles();
        $context = $this->enableHistory($this->createNetworkContext());
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-att@test.com');
        $subject = $this->createCase($context)->subject;
        $type = HistoryEntryType::create([
            'network_id' => $context['network']->id,
            'key' => 'note',
            'label' => 'Nota',
            'is_active' => true,
        ]);
        $entry = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $subject->id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Con archivo',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);
        \App\Support\History\HistoryAttachments::attachFromUploads(
            $entry,
            [\Illuminate\Http\UploadedFile::fake()->create('lab.pdf', 20, 'application/pdf')],
        );
        $media = $entry->fresh()->getFirstMedia('attachments');
        $this->assertNotNull($media);

        $this->actingAs($operator)
            ->get(route('history.attachments.show', [$entry, $media]))
            ->assertOk();

        $otherOrg = $context['organization']->replicate();
        $otherOrg->slug = 'otra-att';
        $otherOrg->history_enabled = true;
        $otherOrg->save();
        $otherOp = $this->createUserWithRole($context['network'], 'operator', $otherOrg, 'op-att-b@test.com');

        $this->actingAs($otherOp)
            ->get(route('history.attachments.show', [$entry, $media]))
            ->assertForbidden();

        $specialist = $this->createUserWithRole($context['network'], 'specialist', $context['organization'], 'esp-att@test.com');
        $this->actingAs($specialist)
            ->get(route('history.attachments.show', [$entry, $media]))
            ->assertForbidden();

        $networkAdmin = $this->createUserWithRole($context['network'], 'network_admin', null, 'na-att@test.com');
        $this->actingAs($networkAdmin)
            ->get(route('history.attachments.show', [$entry, $media]))
            ->assertForbidden();

        $otherNetwork = $this->createNetworkContext('otra-red');
        $otherNetwork['network']->update([
            'settings' => array_replace_recursive(NetworkSettings::defaults(), [
                'modules' => ['history_enabled' => true],
            ]),
        ]);
        $otherNetwork['organization']->update(['history_enabled' => true]);
        $foreignOp = $this->createUserWithRole($otherNetwork['network'], 'operator', $otherNetwork['organization'], 'op-att-red@test.com');
        $foreignStatus = $this->actingAs($foreignOp)
            ->get(route('history.attachments.show', [$entry, $media]))
            ->status();
        $this->assertContains($foreignStatus, [403, 404]);
    }

    public function test_draft_can_be_deleted_and_addendum_keeps_share_opt_in(): void
    {
        $this->seedRoles();
        $context = $this->enableHistory($this->createNetworkContext());
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-del@test.com');
        $case = $this->createCase($context);
        $type = HistoryEntryType::create([
            'network_id' => $context['network']->id,
            'key' => 'note',
            'label' => 'Nota',
            'is_active' => true,
        ]);
        $draft = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $case->subject_id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Borrar',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);
        $draft->delete();
        $this->assertDatabaseMissing('subject_history_entries', ['id' => $draft->id]);

        $final = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $case->subject_id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Interno',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);
        app(FinalizeHistoryEntry::class)->handle($final, $operator);
        app(CreateHistoryAddendum::class)->handle($final->fresh(), $operator, 'Corrección interna');

        $this->actingAs($operator);
        $this->assertTrue(HistoryEntriesRelationManager::canViewForRecord($case->subject, ViewSubject::class));
        $this->assertCount(0, HistoryAccess::sharedEntriesForCase($case));
    }

    protected function enableHistory(array $context): array
    {
        $context['network']->update([
            'settings' => array_replace_recursive(NetworkSettings::defaults(), [
                'modules' => ['history_enabled' => true],
            ]),
        ]);
        $context['organization']->update(['history_enabled' => true]);

        return $context;
    }

    protected function createPlatformOwner(): \App\Models\User
    {
        $owner = new \App\Models\User;
        $owner->forceFill([
            'name' => 'Owner',
            'email' => 'owner-ficha@test.com',
            'password' => 'password',
            'is_platform_owner' => true,
            'is_active' => true,
        ])->save();

        return $owner;
    }
}
