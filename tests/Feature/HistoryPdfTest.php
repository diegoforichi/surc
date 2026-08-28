<?php

namespace Tests\Feature;

use App\Actions\History\CreateHistoryAddendum;
use App\Actions\History\FinalizeHistoryEntry;
use App\Actions\History\ShareHistoryEntry;
use App\Filament\Resources\HistoryEntryTypeResource;
use App\Filament\Resources\SubjectResource;
use App\Filament\Resources\SubjectResource\Pages\ViewSubject;
use App\Filament\Resources\SubjectResource\RelationManagers\HistoryEntriesRelationManager;
use App\Models\HistoryEntryType;
use App\Models\SubjectHistoryEntry;
use App\Support\History\HistoryAccess;
use App\Support\History\HistoryDocuments;
use App\Support\Settings\NetworkSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class HistoryPdfTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    public function test_operator_downloads_final_entry_pdf_and_records_audit(): void
    {
        [$context, $operator, $case, $final] = $this->preparedHistory();
        $this->shareAndAddendum($context, $operator, $case, $final);

        $response = $this->actingAs($operator)->get(route('history.entries.pdf', $final));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertDatabaseHas('activity_log', [
            'event' => 'history_entry_pdf_downloaded',
            'causer_id' => $operator->id,
            'subject_type' => SubjectHistoryEntry::class,
            'subject_id' => $final->id,
        ]);
    }

    public function test_complete_ficha_pdf_excludes_drafts_and_keeps_addenda_grouped(): void
    {
        [$context, $operator, $case, $final] = $this->preparedHistory();
        $addendum = $this->shareAndAddendum($context, $operator, $case, $final);

        SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $case->subject_id,
            'history_entry_type_id' => $final->history_entry_type_id,
            'occurred_at' => now(),
            'summary' => 'Borrador secreto',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);

        $subject = $case->subject->fresh();
        $timeline = HistoryDocuments::finalTimeline($subject);

        $this->assertCount(1, $timeline);
        $this->assertSame($final->id, $timeline->first()->id);
        $this->assertTrue($timeline->first()->addenda->contains('id', $addendum->id));

        $html = view('history.subject', [
            'title' => 'Ficha de historial',
            'subject' => $subject->load(['organization', 'owner']),
            'entries' => $timeline,
            'issuedBy' => $operator->name,
            'issuedAt' => now(),
        ])->render();

        $this->assertStringContainsString('Registro final visible', $html);
        $this->assertStringContainsString('Corrección de adenda', $html);
        $this->assertStringContainsString($case->code, $html);
        $this->assertStringContainsString('Copia interna', $html);
        $this->assertStringContainsString('Kilometraje', $html);
        $this->assertStringNotContainsString('Borrador secreto', $html);

        $response = $this->actingAs($operator)->get(route('history.subjects.pdf', $subject));
        $response->assertOk();
        $this->assertDatabaseHas('activity_log', [
            'event' => 'subject_history_pdf_downloaded',
            'causer_id' => $operator->id,
        ]);
    }

    public function test_draft_and_unauthorized_profiles_cannot_print(): void
    {
        [$context, $operator, $case, $final] = $this->preparedHistory();
        $draft = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $case->subject_id,
            'history_entry_type_id' => $final->history_entry_type_id,
            'occurred_at' => now(),
            'summary' => 'Aún no',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);

        $this->actingAs($operator)
            ->get(route('history.entries.pdf', $draft))
            ->assertForbidden();

        $networkAdmin = $this->createUserWithRole($context['network'], 'network_admin', null, 'na-pdf@test.com');
        $this->actingAs($networkAdmin)
            ->get(route('history.entries.pdf', $final))
            ->assertForbidden();
        $this->actingAs($networkAdmin)
            ->get(route('history.subjects.pdf', $case->subject))
            ->assertForbidden();

        $specialist = $this->createUserWithRole($context['network'], 'specialist', $context['organization'], 'esp-pdf@test.com');
        $this->actingAs($specialist)
            ->get(route('history.entries.pdf', $final))
            ->assertForbidden();

        $otherOrg = $context['organization']->replicate();
        $otherOrg->slug = 'otra-pdf';
        $otherOrg->history_enabled = true;
        $otherOrg->save();
        $otherOp = $this->createUserWithRole($context['network'], 'operator', $otherOrg, 'op-pdf-b@test.com');
        $response = $this->actingAs($otherOp)
            ->get(route('history.subjects.pdf', $case->subject));
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_network_admin_configures_types_but_cannot_open_history_notebook(): void
    {
        [$context, $operator, $case] = $this->preparedHistory();
        $admin = $this->createUserWithRole($context['network'], 'network_admin', null, 'na-hist@test.com');
        $admin->givePermissionTo('history.view');

        $this->actingAs($admin);
        $this->assertTrue(SubjectResource::canView($case->subject));
        $this->assertTrue(SubjectResource::canEdit($case->subject));
        $this->assertTrue(HistoryEntryTypeResource::canViewAny());
        $this->assertFalse(HistoryAccess::canViewSubject($admin, $case->subject));
        $this->assertFalse(HistoryEntriesRelationManager::canViewForRecord($case->subject, ViewSubject::class));
        $this->assertFalse($admin->can('history.print'));
        $this->assertTrue($operator->can('history.print'));
    }

    /**
     * @return array{0: array<string, mixed>, 1: \App\Models\User, 2: \App\Models\CaseRecord, 3: SubjectHistoryEntry}
     */
    protected function preparedHistory(): array
    {
        $this->seedRoles();
        $context = $this->enableHistory($this->createNetworkContext());
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-pdf@test.com');
        $case = $this->createCase($context);
        $type = HistoryEntryType::create([
            'network_id' => $context['network']->id,
            'key' => 'inspection',
            'label' => 'Inspección',
            'is_active' => true,
            'field_schema' => [
                ['key' => 'mileage', 'label' => 'Kilometraje', 'type' => 'number'],
            ],
        ]);
        $entry = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $case->subject_id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now()->subDay(),
            'summary' => 'Registro final visible',
            'payload' => ['mileage' => 1200],
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);
        app(FinalizeHistoryEntry::class)->handle($entry, $operator);

        return [$context, $operator, $case, $entry->fresh()];
    }

    protected function shareAndAddendum(array $context, $operator, $case, SubjectHistoryEntry $final): SubjectHistoryEntry
    {
        app(ShareHistoryEntry::class)->handle($final, $case, $operator);

        return app(CreateHistoryAddendum::class)->handle($final, $operator, 'Corrección de adenda');
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
