<?php

namespace Tests\Feature;

use App\Actions\History\FinalizeHistoryEntry;
use App\Filament\Resources\SubjectResource\Pages\ViewSubjectHistory;
use App\Filament\Widgets\UpcomingHistoryRemindersWidget;
use App\Livewire\SubjectHistoryTimeline;
use App\Models\HistoryEntryType;
use App\Models\Subject;
use App\Models\SubjectHistoryEntry;
use App\Models\User;
use App\Support\History\HistoryAccess;
use App\Support\Settings\NetworkSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class HistoryNotebookTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    public function test_finalize_requires_schema_fields_but_draft_can_be_incomplete(): void
    {
        [$context, $operator, $subject, $type] = $this->prepared();

        $entry = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $subject->id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => null,
            'payload' => ['weight' => 8],
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);

        try {
            app(FinalizeHistoryEntry::class)->handle($entry, $operator);
            $this->fail('Se esperaba validación al finalizar incompleto.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $entry->update([
            'payload' => ['findings' => 'Otitis', 'weight' => 8],
        ]);
        $final = app(FinalizeHistoryEntry::class)->handle($entry->fresh(), $operator);

        $this->assertTrue($final->isFinal());
        $this->assertSame('Otitis', $final->summary);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'history_entry_finalized',
            'subject_id' => $final->id,
        ]);
    }

    public function test_next_due_at_is_projected_from_payload(): void
    {
        [$context, $operator, $subject, $type] = $this->prepared();

        $entry = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $subject->id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Control',
            'payload' => ['findings' => 'Ok', 'next_due' => '2026-09-15'],
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);

        $this->assertSame('2026-09-15', $entry->fresh()->next_due_at?->toDateString());
    }

    public function test_mismatched_organization_on_entry_is_blocked(): void
    {
        [$context, $operator, $subject, $type] = $this->prepared();
        $otherOrg = $context['organization']->replicate();
        $otherOrg->slug = 'otra-nb';
        $otherOrg->history_enabled = true;
        $otherOrg->save();

        $entry = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $otherOrg->id,
            'subject_id' => $subject->id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Ajeno',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);

        $this->assertFalse(HistoryAccess::canViewEntry($operator, $entry));
        $this->assertFalse(
            HistoryAccess::entriesQueryForSubject($subject)->whereKey($entry->id)->exists()
        );
    }

    public function test_timeline_is_only_for_own_clinic_and_network_admin_is_blocked(): void
    {
        [$context, $operator, $subject] = $this->prepared();

        $this->actingAs($operator);
        Livewire::test(ViewSubjectHistory::class, ['record' => $subject->getKey()])
            ->assertSuccessful();
        Livewire::test(SubjectHistoryTimeline::class, ['subject' => $subject])
            ->assertSuccessful();

        $admin = $this->createUserWithRole($context['network'], 'network_admin', null, 'na-nb@test.com');
        $this->actingAs($admin);
        Livewire::test(ViewSubjectHistory::class, ['record' => $subject->getKey()])
            ->assertForbidden();
    }

    public function test_reminders_include_only_final_entries_of_own_clinic(): void
    {
        [$context, $operator, $subject, $type] = $this->prepared();
        $due = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $subject->id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Vacuna',
            'payload' => ['findings' => 'Ok', 'next_due' => now()->addDays(3)->toDateString()],
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);
        app(FinalizeHistoryEntry::class)->handle($due, $operator);

        SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $subject->id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Borrador con fecha',
            'payload' => ['next_due' => now()->addDay()->toDateString()],
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);

        $otherOrg = $context['organization']->replicate();
        $otherOrg->slug = 'otra-rem';
        $otherOrg->history_enabled = true;
        $otherOrg->save();
        $otherSubject = $this->createCase(array_merge($context, ['organization' => $otherOrg]), [
            'organization_id' => $otherOrg->id,
        ])->subject;
        $otherOp = $this->createUserWithRole($context['network'], 'operator', $otherOrg, 'op-rem-b@test.com');
        $foreign = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $otherOrg->id,
            'subject_id' => $otherSubject->id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Ajena',
            'payload' => ['findings' => 'Ok', 'next_due' => now()->addDays(2)->toDateString()],
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $otherOp->id,
        ]);
        app(FinalizeHistoryEntry::class)->handle($foreign, $otherOp);

        $this->actingAs($operator);
        $this->assertTrue(UpcomingHistoryRemindersWidget::canView());
        $ids = HistoryAccess::remindersQueryForUser($operator)->pluck('id');
        $this->assertTrue($ids->contains($due->id));
        $this->assertFalse($ids->contains($foreign->id));
    }

    /**
     * @return array{0: array<string, mixed>, 1: User, 2: Subject, 3: HistoryEntryType}
     */
    protected function prepared(): array
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $context['network']->update([
            'settings' => array_replace_recursive(NetworkSettings::defaults(), [
                'modules' => ['history_enabled' => true],
            ]),
        ]);
        $context['organization']->update(['history_enabled' => true]);
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-nb@test.com');
        $subject = $this->createCase($context)->subject;
        $type = HistoryEntryType::create([
            'network_id' => $context['network']->id,
            'key' => 'consultation',
            'label' => 'Consulta',
            'is_active' => true,
            'field_schema' => [
                ['key' => 'findings', 'label' => 'Hallazgos', 'type' => 'textarea', 'required' => true],
                ['key' => 'weight', 'label' => 'Peso', 'type' => 'number'],
                ['key' => 'next_due', 'label' => 'Próxima fecha', 'type' => 'date'],
            ],
        ]);

        return [$context, $operator, $subject, $type];
    }
}
