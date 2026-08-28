<?php

namespace App\Actions\Sales;

use App\Models\Organization;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\History\HistoryAudit;
use App\Support\Sales\OrganizationSalesSettings;
use App\Support\Sales\SalesAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueSalesOrder
{
    public function handle(SalesOrder $order, User $user): SalesOrder
    {
        if (! SalesAccess::canViewOrder($user, $order) || ! $order->isDraft()) {
            abort(403);
        }

        $order->loadMissing('lines');

        if ($order->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Agregue al menos una línea antes de emitir la orden.',
            ]);
        }

        foreach ($order->lines as $line) {
            if (! filled($line->description) || (float) $line->quantity <= 0) {
                throw ValidationException::withMessages([
                    'lines' => 'Cada línea necesita descripción y cantidad mayor a cero.',
                ]);
            }
        }

        return DB::transaction(function () use ($order, $user): SalesOrder {
            $organization = Organization::query()
                ->whereKey($order->organization_id)
                ->lockForUpdate()
                ->firstOrFail();

            SalesOrder::query()
                ->where('organization_id', $organization->id)
                ->whereNotNull('number')
                ->lockForUpdate()
                ->get();

            $prefix = OrganizationSalesSettings::get($organization, 'order_prefix', 'OV');
            $latest = SalesOrder::query()
                ->where('organization_id', $organization->id)
                ->where('number', 'like', $prefix.'-%')
                ->orderByDesc('id')
                ->value('number');

            $sequence = 1;

            if (is_string($latest) && preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', $latest, $matches) === 1) {
                $sequence = ((int) $matches[1]) + 1;
            }

            do {
                $candidate = sprintf('%s-%06d', $prefix, $sequence);
                $exists = SalesOrder::query()
                    ->where('organization_id', $organization->id)
                    ->where('number', $candidate)
                    ->exists();
                $sequence++;
            } while ($exists);

            $order->recalculateTotals();
            $order->number = $candidate;
            $order->status = SalesOrder::STATUS_ISSUED;
            $order->issued_by = $user->id;
            $order->issued_at = now();
            $order->save();

            HistoryAudit::log('sales_order_issued', $order, [
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'number' => $order->number,
            ]);

            return $order->fresh(['lines']);
        });
    }
}
