<?php

namespace App\Actions\Sales;

use App\Models\SalesOrder;
use App\Models\User;
use App\Support\History\HistoryAudit;
use App\Support\Sales\SalesAccess;
use App\Support\Sales\SalesOrderCsv;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSalesOrder
{
    public function csv(SalesOrder $order, User $user): StreamedResponse
    {
        $this->markExported($order, $user);

        $filename = 'orden-'.($order->number ?: $order->export_uid).'.csv';
        $contents = SalesOrderCsv::contents($order->fresh(['lines']));

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function markExported(SalesOrder $order, User $user): SalesOrder
    {
        if (! SalesAccess::canExportOrders($user, $order->organization) || ! SalesAccess::canViewOrder($user, $order)) {
            abort(403);
        }

        if (! $order->isLocked()) {
            throw ValidationException::withMessages([
                'status' => 'Emita la orden antes de exportarla.',
            ]);
        }

        $order->exported_by = $user->id;
        $order->exported_at = now();

        if ($order->status === SalesOrder::STATUS_ISSUED) {
            $order->status = SalesOrder::STATUS_EXPORTED;
        }

        $order->save();

        HistoryAudit::log('sales_order_exported', $order, [
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
            'number' => $order->number,
            'export_uid' => $order->export_uid,
        ]);

        return $order->fresh(['lines']);
    }
}
