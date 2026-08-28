<?php

namespace App\Actions\Sales;

use App\Models\SalesOrder;
use App\Models\User;
use App\Support\History\HistoryAudit;
use App\Support\Sales\SalesAccess;
use Illuminate\Validation\ValidationException;

class CancelSalesOrder
{
    public function handle(SalesOrder $order, User $user): SalesOrder
    {
        if (! SalesAccess::canViewOrder($user, $order)) {
            abort(403);
        }

        if (! $order->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Solo se puede anular una orden en borrador.',
            ]);
        }

        $order->status = SalesOrder::STATUS_CANCELLED;
        $order->cancelled_by = $user->id;
        $order->cancelled_at = now();
        $order->save();

        HistoryAudit::log('sales_order_cancelled', $order, [
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
        ]);

        return $order->fresh();
    }
}
