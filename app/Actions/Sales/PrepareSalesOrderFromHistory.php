<?php

namespace App\Actions\Sales;

use App\Models\Payment;
use App\Models\SalesCatalogItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SubjectHistoryEntry;
use App\Models\User;
use App\Support\History\HistoryAccess;
use App\Support\History\HistoryAudit;
use App\Support\Sales\OrganizationSalesSettings;
use App\Support\Sales\SalesAccess;
use Illuminate\Validation\ValidationException;

class PrepareSalesOrderFromHistory
{
    public function handle(SubjectHistoryEntry $entry, User $user): SalesOrder
    {
        $entry->loadMissing(['subject.owner', 'subject.organization', 'sourceCase', 'type']);

        if (! $entry->isFinal() || $entry->addendum_of_id !== null) {
            throw ValidationException::withMessages([
                'status' => 'La orden de venta solo se emite desde un registro clínico final.',
            ]);
        }

        if (! HistoryAccess::canViewEntry($user, $entry) || ! SalesAccess::canManageOrders($user, $entry->organization)) {
            abort(403);
        }

        $existing = SalesOrder::query()
            ->where('subject_history_entry_id', $entry->id)
            ->whereIn('status', [
                SalesOrder::STATUS_DRAFT,
                SalesOrder::STATUS_ISSUED,
                SalesOrder::STATUS_EXPORTED,
            ])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $subject = $entry->subject;
        $organization = $entry->organization;
        $settings = OrganizationSalesSettings::all($organization);
        $owner = $subject?->owner;

        $order = SalesOrder::create([
            'network_id' => $entry->network_id,
            'organization_id' => $entry->organization_id,
            'subject_id' => $entry->subject_id,
            'subject_history_entry_id' => $entry->id,
            'owner_party_id' => $subject?->owner_party_id,
            'source_case_id' => $entry->source_case_id,
            'status' => SalesOrder::STATUS_DRAFT,
            'currency' => $settings['currency'],
            'subject_snapshot' => [
                'id' => $subject?->id,
                'label_name' => $subject?->label_name,
                'code' => $subject?->code,
            ],
            'client_snapshot' => [
                'id' => $owner?->id,
                'display_name' => $owner?->display_name,
                'document_id' => $owner?->document_id,
                'email' => $owner?->email,
                'phone' => $owner?->phone,
            ],
            'organization_snapshot' => [
                'id' => $organization?->id,
                'name' => $organization?->name,
                'address' => $organization?->address,
                'phone' => $organization?->phone,
                'email' => $organization?->email,
                'issuer_name' => $settings['issuer_name'] ?: $organization?->name,
                'issuer_document' => $settings['issuer_document'],
                'issuer_address' => $settings['issuer_address'] ?: $organization?->address,
            ],
            'deposit_reference' => $this->depositReference($entry),
            'created_by' => $user->id,
        ]);

        $items = SalesCatalogItem::query()
            ->where('organization_id', $entry->organization_id)
            ->where('network_id', $entry->network_id)
            ->where('is_active', true)
            ->where('history_entry_type_id', $entry->history_entry_type_id)
            ->orderBy('code')
            ->get();

        foreach ($items as $index => $item) {
            SalesOrderLine::create([
                'sales_order_id' => $order->id,
                'sales_catalog_item_id' => $item->id,
                'sort_order' => $index,
                'code' => $item->code,
                'description' => $item->description,
                'quantity' => 1,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate,
                'is_manual' => false,
            ]);
        }

        $order->recalculateTotals();
        $order->save();

        HistoryAudit::log('sales_order_created', $order, [
            'organization_id' => $order->organization_id,
            'entry_id' => $entry->id,
            'order_id' => $order->id,
        ]);

        return $order->fresh(['lines']);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function depositReference(SubjectHistoryEntry $entry): ?array
    {
        $caseId = $entry->source_case_id;

        if ($caseId === null) {
            return null;
        }

        $payment = Payment::query()
            ->where('case_id', $caseId)
            ->where('type', 'deposit')
            ->where('status', 'confirmed')
            ->orderByDesc('id')
            ->first();

        if ($payment === null) {
            return null;
        }

        return [
            'case_id' => $caseId,
            'case_code' => $entry->sourceCase?->code,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'method' => $payment->method,
        ];
    }
}
