<?php

namespace App\Http\Controllers;

use App\Actions\Sales\ExportSalesOrder;
use App\Models\SalesOrder;
use App\Support\History\HistoryAudit;
use App\Support\Sales\SalesAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesOrderReportController extends Controller
{
    public function pdf(SalesOrder $order): Response
    {
        $user = Auth::user();
        abort_unless(SalesAccess::canViewOrder($user, $order), 403);
        abort_unless($order->isLocked(), 403);
        abort_unless(SalesAccess::canExportOrders($user, $order->organization), 403);

        $order->load(['lines']);

        HistoryAudit::log('sales_order_pdf_downloaded', $order, [
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
            'number' => $order->number,
            'export_uid' => $order->export_uid,
        ]);

        $pdf = Pdf::loadView('sales.order', [
            'order' => $order,
            'issuedBy' => $user?->name ?? '—',
            'issuedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('orden-'.($order->number ?: $order->id).'.pdf');
    }

    public function csv(SalesOrder $order): StreamedResponse
    {
        return app(ExportSalesOrder::class)->csv($order, Auth::user());
    }
}
