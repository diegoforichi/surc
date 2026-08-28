<?php

namespace Tests\Feature;

use App\Actions\History\CreateHistoryAddendum;
use App\Actions\History\FinalizeHistoryEntry;
use App\Actions\History\IncorporateCaseIntoHistory;
use App\Actions\History\ShareHistoryEntry;
use App\Enums\CaseStatus;
use App\Models\HistoryEntryType;
use App\Models\SubjectHistoryEntry;
use App\Support\History\HistoryAccess;
use App\Support\Settings\NetworkSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class SubjectHistoryTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    public function test_history_is_disabled_until_network_and_organization_opt_in(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-hist@test.com');
        $case = $this->createCase($context);

        $this->assertFalse(HistoryAccess::canViewSubject($operator, $case->subject));

        $context['network']->update([
            'settings' => array_replace_recursive(NetworkSettings::defaults(), [
                'modules' => ['history_enabled' => true],
            ]),
        ]);
        $context['organization']->update(['history_enabled' => true]);

        $this->assertTrue(HistoryAccess::canViewSubject($operator->fresh(), $case->subject->fresh()));
    }

    public function test_platform_owner_cannot_read_history_content(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $context['network']->update([
            'settings' => array_replace_recursive(NetworkSettings::defaults(), [
                'modules' => ['history_enabled' => true],
            ]),
        ]);
        $context['organization']->update(['history_enabled' => true]);
        $case = $this->createCase($context);

        $owner = new \App\Models\User;
        $owner->forceFill([
            'name' => 'Owner',
            'email' => 'owner-hist@test.com',
            'password' => 'password',
            'is_platform_owner' => true,
            'is_active' => true,
        ])->save();
        $owner->assignRole('platform_owner');

        $this->assertFalse(HistoryAccess::canViewSubject($owner, $case->subject));
        $this->assertFalse($owner->can('history.view'));
    }

    public function test_final_entries_are_immutable_and_accept_addenda(): void
    {
        $this->seedRoles();
        $context = $this->enableHistory($context = $this->createNetworkContext());
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-add@test.com');
        $case = $this->createCase($context);
        $type = HistoryEntryType::create([
            'network_id' => $context['network']->id,
            'key' => 'note',
            'label' => 'Nota',
            'is_active' => true,
        ]);

        $entry = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $case->subject_id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Borrador',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);

        app(FinalizeHistoryEntry::class)->handle($entry, $operator);

        $this->expectException(ValidationException::class);
        $entry->fresh()->update(['summary' => 'hack']);
    }

    public function test_only_final_entries_can_be_shared_with_matching_case(): void
    {
        $this->seedRoles();
        $context = $this->enableHistory($this->createNetworkContext());
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-share@test.com');
        $case = $this->createCase($context);
        $type = HistoryEntryType::create([
            'network_id' => $context['network']->id,
            'key' => 'note',
            'label' => 'Nota',
            'is_active' => true,
        ]);

        $entry = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $case->subject_id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Borrador',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);

        try {
            app(ShareHistoryEntry::class)->handle($entry, $case, $operator);
            $this->fail('Se esperaba una excepción al compartir un borrador.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        app(FinalizeHistoryEntry::class)->handle($entry, $operator);
        $share = app(ShareHistoryEntry::class)->handle($entry->fresh(), $case, $operator);

        $this->assertSame($case->id, $share->case_id);
        $this->assertSame($entry->id, $share->subject_history_entry_id);
        $this->assertCount(1, HistoryAccess::sharedEntriesForCase($case));
    }

    public function test_addendum_and_case_incorporation(): void
    {
        $this->seedRoles();
        $context = $this->enableHistory($this->createNetworkContext());
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-inc@test.com');
        $case = $this->createCase($context, ['summary' => 'Resumen de derivación']);
        HistoryEntryType::create([
            'network_id' => $context['network']->id,
            'key' => 'note',
            'label' => 'Nota',
            'is_active' => true,
        ]);

        $entry = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $case->subject_id,
            'history_entry_type_id' => HistoryEntryType::query()->first()->id,
            'occurred_at' => now(),
            'summary' => 'Final',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);
        app(FinalizeHistoryEntry::class)->handle($entry, $operator);

        $addendum = app(CreateHistoryAddendum::class)->handle($entry->fresh(), $operator, 'Corrección');
        $this->assertTrue($addendum->isFinal());
        $this->assertSame($entry->id, $addendum->addendum_of_id);

        try {
            app(IncorporateCaseIntoHistory::class)->handle($case, $operator);
            $this->fail('Se esperaba una excepción al incorporar un caso abierto.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $case->update([
            'status' => CaseStatus::Closed,
            'closed_at' => now(),
            'summary' => 'Resumen de derivación',
        ]);

        $incorporated = app(IncorporateCaseIntoHistory::class)->handle($case->fresh(), $operator);
        $this->assertTrue($incorporated->isFinal());
        $this->assertSame($case->id, $incorporated->source_case_id);
        $this->assertSame($incorporated->id, app(IncorporateCaseIntoHistory::class)->handle($case->fresh(), $operator)->id);
        $this->assertCount(1, HistoryAccess::sharedEntriesForCase($case->fresh()));
    }

    public function test_other_organization_cannot_view_history(): void
    {
        $this->seedRoles();
        $context = $this->enableHistory($this->createNetworkContext());
        $case = $this->createCase($context);

        $otherOrg = $context['organization']->replicate();
        $otherOrg->slug = 'otra-hist';
        $otherOrg->history_enabled = true;
        $otherOrg->save();

        $otherOp = $this->createUserWithRole($context['network'], 'operator', $otherOrg, 'op-otra-h@test.com');

        $this->assertFalse(HistoryAccess::canViewSubject($otherOp, $case->subject));
    }

    public function test_network_admin_cannot_read_or_share_history_even_with_accidental_permission(): void
    {
        $this->seedRoles();
        $context = $this->enableHistory($this->createNetworkContext());
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-na@test.com');
        $admin = $this->createUserWithRole($context['network'], 'network_admin', null, 'na-block@test.com');
        $case = $this->createCase($context);
        $type = HistoryEntryType::create([
            'network_id' => $context['network']->id,
            'key' => 'note',
            'label' => 'Nota',
            'is_active' => true,
        ]);
        $entry = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $case->subject_id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Privado',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);
        app(FinalizeHistoryEntry::class)->handle($entry, $operator);

        $this->assertFalse($admin->can('history.view'));
        $this->assertFalse($admin->can('history.share'));
        $this->assertFalse(HistoryAccess::canViewSubject($admin, $case->subject));
        $this->assertFalse(HistoryAccess::canShareCase($admin, $case));

        $admin->givePermissionTo(['history.view', 'history.share', 'history.print']);
        $admin = $admin->fresh();
        $this->assertFalse(HistoryAccess::canViewSubject($admin, $case->subject));
        $this->assertFalse(HistoryAccess::canPrintEntry($admin, $entry->fresh()));
        $this->assertFalse(HistoryAccess::canShareCase($admin, $case));
        $this->assertTrue(HistoryAccess::canViewSubject($operator, $case->subject));
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
}
