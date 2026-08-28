<?php

namespace Tests\Feature;

use App\Actions\History\FinalizeHistoryEntry;
use App\Actions\Sales\CancelSalesOrder;
use App\Actions\Sales\IssueSalesOrder;
use App\Actions\Sales\PrepareSalesOrderFromHistory;
use App\Filament\Resources\SalesCatalogItemResource;
use App\Filament\Resources\SalesOrderResource;
use App\Models\HistoryEntryType;
use App\Models\Party;
use App\Models\Payment;
use App\Models\SalesCatalogItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Subject;
use App\Models\SubjectHistoryEntry;
use App\Models\User;
use App\Support\Sales\SalesAccess;
use App\Support\Sales\SalesOrderCsv;
use App\Support\Settings\NetworkSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    public function test_catalog_is_isolated_by_clinic(): void
    {
        [$context, $admin, $otherAdmin] = $this->twoAdmins();

        $item = SalesCatalogItem::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'code' => 'CONS',
            'kind' => SalesCatalogItem::KIND_SERVICE,
            'description' => 'Consulta',
            'unit' => 'un',
            'unit_price' => 1500,
            'tax_rate' => 22,
            'currency' => 'UYU',
            'is_active' => true,
        ]);

        $this->actingAs($admin);
        $this->assertTrue(SalesCatalogItemResource::canViewAny());
        $this->assertTrue(SalesAccess::canManageCatalog($admin, $context['organization']));
        $this->assertTrue(SalesCatalogItemResource::getEloquentQuery()->whereKey($item->id)->exists());

        $this->actingAs($otherAdmin);
        $this->assertTrue(SalesCatalogItemResource::canViewAny());
        $this->assertFalse(SalesAccess::canManageCatalog($otherAdmin, $context['organization']));
        $this->assertFalse(SalesCatalogItemResource::getEloquentQuery()->whereKey($item->id)->exists());
    }

    public function test_order_is_created_only_from_final_entry_and_is_idempotent(): void
    {
        [$context, $operator, $entry, $type] = $this->finalEntry();

        SalesCatalogItem::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'code' => 'VAC',
            'kind' => SalesCatalogItem::KIND_PRODUCT,
            'description' => 'Vacuna',
            'unit' => 'dosis',
            'unit_price' => 800,
            'tax_rate' => 0,
            'currency' => 'UYU',
            'history_entry_type_id' => $type->id,
            'is_active' => true,
        ]);

        $draft = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $entry->subject_id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Borrador',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);

        try {
            app(PrepareSalesOrderFromHistory::class)->handle($draft, $operator);
            $this->fail('No debería crearse orden desde un borrador.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $first = app(PrepareSalesOrderFromHistory::class)->handle($entry, $operator);
        $second = app(PrepareSalesOrderFromHistory::class)->handle($entry->fresh(), $operator);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(SalesOrder::STATUS_DRAFT, $first->status);
        $this->assertSame('VAC', $first->lines->first()?->code);
        $this->assertSame($entry->subject->label_name, $first->subject_snapshot['label_name']);
        $this->assertStringNotContainsString('Otitis', SalesOrderCsv::contents($first));
    }

    public function test_issue_assigns_concurrent_numbers_and_locks_the_order(): void
    {
        [$context, $operator, $entry] = $this->finalEntry();
        $order = app(PrepareSalesOrderFromHistory::class)->handle($entry, $operator);
        SalesOrderLine::create([
            'sales_order_id' => $order->id,
            'description' => 'Consulta manual',
            'quantity' => 1,
            'unit' => 'un',
            'unit_price' => 1000,
            'tax_rate' => 22,
            'is_manual' => true,
        ]);
        $order->recalculateTotals();
        $order->save();

        $issued = app(IssueSalesOrder::class)->handle($order->fresh(), $operator);
        $this->assertSame('OV-000001', $issued->number);
        $this->assertSame(SalesOrder::STATUS_ISSUED, $issued->status);
        $this->assertEquals(1220.0, (float) $issued->total);

        $this->expectException(ValidationException::class);
        $issued->update(['notes' => 'hack']);
    }

    public function test_csv_is_deterministic_and_reexport_is_audited(): void
    {
        [$context, $operator, $entry] = $this->finalEntry();
        $order = app(PrepareSalesOrderFromHistory::class)->handle($entry, $operator);
        SalesOrderLine::create([
            'sales_order_id' => $order->id,
            'code' => 'MAN',
            'description' => 'Servicio',
            'quantity' => 2,
            'unit' => 'un',
            'unit_price' => 100,
            'tax_rate' => 0,
            'is_manual' => true,
        ]);
        $issued = app(IssueSalesOrder::class)->handle($order->fresh(), $operator);

        $csvA = SalesOrderCsv::contents($issued);
        $csvB = SalesOrderCsv::contents($issued->fresh(['lines']));
        $this->assertSame($csvA, $csvB);
        $this->assertStringContainsString($issued->export_uid, $csvA);
        $this->assertStringContainsString('Servicio', $csvA);
        $this->assertStringNotContainsString('Otitis', $csvA);
        $this->assertStringNotContainsString('lab.pdf', $csvA);

        $response = $this->actingAs($operator)->get(route('sales.orders.csv', $issued));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertSame(SalesOrder::STATUS_EXPORTED, $issued->fresh()->status);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'sales_order_exported',
            'subject_id' => $issued->id,
        ]);

        $this->actingAs($operator)->get(route('sales.orders.csv', $issued->fresh()))->assertOk();
        $this->assertSame(2, Activity::query()->where('event', 'sales_order_exported')->count());

        $pdf = $this->actingAs($operator)->get(route('sales.orders.pdf', $issued));
        $pdf->assertOk();
        $this->assertDatabaseHas('activity_log', [
            'event' => 'sales_order_pdf_downloaded',
            'subject_id' => $issued->id,
        ]);
    }

    public function test_permissions_block_specialist_network_admin_and_other_clinic(): void
    {
        [$context, $operator, $entry] = $this->finalEntry();
        $order = app(PrepareSalesOrderFromHistory::class)->handle($entry, $operator);
        SalesOrderLine::create([
            'sales_order_id' => $order->id,
            'description' => 'Consulta',
            'quantity' => 1,
            'unit' => 'un',
            'unit_price' => 10,
            'is_manual' => true,
        ]);
        $issued = app(IssueSalesOrder::class)->handle($order->fresh(), $operator);

        $specialist = $this->createUserWithRole($context['network'], 'specialist', $context['organization'], 'esp-so@test.com');
        $this->actingAs($specialist);
        $this->assertFalse(SalesOrderResource::canViewAny());
        $this->assertFalse(SalesAccess::canViewOrder($specialist, $issued));

        $admin = $this->createUserWithRole($context['network'], 'network_admin', null, 'na-so@test.com');
        $this->actingAs($admin);
        $this->assertFalse(SalesAccess::canViewOrder($admin, $issued));
        $this->actingAs($admin)->get(route('sales.orders.csv', $issued))->assertForbidden();

        $otherOrg = $context['organization']->replicate();
        $otherOrg->slug = 'otra-so';
        $otherOrg->history_enabled = true;
        $otherOrg->save();
        $otherOp = $this->createUserWithRole($context['network'], 'operator', $otherOrg, 'op-so-b@test.com');
        $this->actingAs($otherOp);
        $this->assertFalse(SalesAccess::canViewOrder($otherOp, $issued));
        $this->actingAs($otherOp)->get(route('sales.orders.pdf', $issued))->assertForbidden();
    }

    public function test_shared_agenda_order_stays_with_origin_clinic(): void
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $context['network']->update([
            'settings' => array_replace_recursive(NetworkSettings::defaults(), [
                'modules' => ['history_enabled' => true],
            ]),
        ]);
        $host = $context['organization'];
        $host->update(['history_enabled' => true]);
        $origin = $host->replicate();
        $origin->slug = 'dumbo';
        $origin->name = 'Dumbo';
        $origin->history_enabled = true;
        $origin->save();

        $hostAdmin = $this->createUserWithRole($context['network'], 'organization_admin', $host, 'animalia@test.com');
        $originAdmin = $this->createUserWithRole($context['network'], 'organization_admin', $origin, 'dumbo@test.com');
        $owner = Party::create([
            'network_id' => $context['network']->id,
            'organization_id' => $origin->id,
            'actor_type_id' => $context['specialistType']->id,
            'display_name' => 'Dueño Dumbo',
            'document_id' => '111',
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'network_id' => $context['network']->id,
            'organization_id' => $origin->id,
            'owner_party_id' => $owner->id,
            'label_name' => 'Paciente Dumbo',
            'is_active' => true,
        ]);
        $type = HistoryEntryType::create([
            'network_id' => $context['network']->id,
            'key' => 'consultation',
            'label' => 'Consulta',
            'is_active' => true,
        ]);
        $entry = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $origin->id,
            'subject_id' => $subject->id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Atendido en agenda abierta',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $originAdmin->id,
        ]);
        app(FinalizeHistoryEntry::class)->handle($entry, $originAdmin);

        $order = app(PrepareSalesOrderFromHistory::class)->handle($entry->fresh(), $originAdmin);
        $this->assertSame($origin->id, $order->organization_id);
        $this->assertTrue(SalesAccess::canViewOrder($originAdmin, $order));
        $this->assertFalse(SalesAccess::canViewOrder($hostAdmin, $order));
        $this->actingAs($hostAdmin);
        $this->assertFalse(SalesOrderResource::getEloquentQuery()->whereKey($order->id)->exists());
    }

    public function test_deposit_is_reference_only_and_cancel_allows_a_new_draft(): void
    {
        [$context, $operator, $entry] = $this->finalEntry();
        $case = $entry->subject->cases()->first();
        Payment::create([
            'network_id' => $context['network']->id,
            'case_id' => $case->id,
            'type' => 'deposit',
            'amount' => 200,
            'currency' => 'UYU',
            'status' => 'confirmed',
            'method' => 'efectivo',
        ]);

        $order = app(PrepareSalesOrderFromHistory::class)->handle($entry->fresh(['sourceCase', 'subject.owner', 'organization']), $operator);
        $this->assertNull($order->deposit_reference);

        $entryWithCase = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $entry->subject_id,
            'history_entry_type_id' => $entry->history_entry_type_id,
            'source_case_id' => $case->id,
            'occurred_at' => now(),
            'summary' => 'Control con seña',
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);
        app(FinalizeHistoryEntry::class)->handle($entryWithCase, $operator);

        $order = app(PrepareSalesOrderFromHistory::class)->handle($entryWithCase->fresh(['sourceCase', 'subject.owner', 'organization']), $operator);
        $this->assertSame(200.0, (float) $order->deposit_reference['amount']);
        app(CancelSalesOrder::class)->handle($order, $operator);
        $this->assertSame(SalesOrder::STATUS_CANCELLED, $order->fresh()->status);

        $again = app(PrepareSalesOrderFromHistory::class)->handle($entryWithCase->fresh(), $operator);
        $this->assertNotSame($order->id, $again->id);
        $this->assertSame(SalesOrder::STATUS_DRAFT, $again->status);
    }

    /**
     * @return array{0: array<string, mixed>, 1: User, 2: User}
     */
    protected function twoAdmins(): array
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $admin = $this->createUserWithRole($context['network'], 'organization_admin', $context['organization'], 'cat-a@test.com');
        $otherOrg = $context['organization']->replicate();
        $otherOrg->slug = 'otra-cat';
        $otherOrg->save();
        $otherAdmin = $this->createUserWithRole($context['network'], 'organization_admin', $otherOrg, 'cat-b@test.com');

        return [$context, $admin, $otherAdmin];
    }

    /**
     * @return array{0: array<string, mixed>, 1: User, 2: SubjectHistoryEntry, 3: HistoryEntryType}
     */
    protected function finalEntry(): array
    {
        $this->seedRoles();
        $context = $this->createNetworkContext();
        $context['network']->update([
            'settings' => array_replace_recursive(NetworkSettings::defaults(), [
                'modules' => ['history_enabled' => true],
            ]),
        ]);
        $context['organization']->update(['history_enabled' => true]);
        $operator = $this->createUserWithRole($context['network'], 'operator', $context['organization'], 'op-so@test.com');
        $case = $this->createCase($context);
        $type = HistoryEntryType::create([
            'network_id' => $context['network']->id,
            'key' => 'consultation',
            'label' => 'Consulta',
            'is_active' => true,
        ]);
        $entry = SubjectHistoryEntry::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'subject_id' => $case->subject_id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => now(),
            'summary' => 'Otitis en consulta',
            'payload' => ['findings' => 'Otitis'],
            'status' => SubjectHistoryEntry::STATUS_DRAFT,
            'author_user_id' => $operator->id,
        ]);
        app(FinalizeHistoryEntry::class)->handle($entry, $operator);

        return [$context, $operator, $entry->fresh(['subject']), $type];
    }
}
