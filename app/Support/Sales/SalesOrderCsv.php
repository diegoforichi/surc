<?php

namespace App\Support\Sales;

use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use League\Csv\Writer;

class SalesOrderCsv
{
    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'order_uid',
            'order_number',
            'organization_id',
            'organization_name',
            'issued_at',
            'client_document',
            'client_name',
            'subject_label',
            'line_code',
            'line_description',
            'quantity',
            'unit',
            'unit_price',
            'tax_rate',
            'line_total',
            'currency',
        ];
    }

    public static function contents(SalesOrder $order): string
    {
        $order->loadMissing('lines');

        $csv = Writer::createFromString();
        $csv->insertOne(self::headers());

        foreach ($order->lines as $line) {
            $csv->insertOne(self::row($order, $line));
        }

        return $csv->toString();
    }

    /**
     * @return list<string|int|float|null>
     */
    public static function row(SalesOrder $order, SalesOrderLine $line): array
    {
        $client = $order->client_snapshot ?? [];
        $subject = $order->subject_snapshot ?? [];
        $organization = $order->organization_snapshot ?? [];

        return [
            $order->export_uid,
            $order->number,
            $order->organization_id,
            $organization['name'] ?? '',
            $order->issued_at?->format('Y-m-d H:i:s') ?? '',
            $client['document_id'] ?? '',
            $client['display_name'] ?? '',
            $subject['label_name'] ?? '',
            $line->code,
            $line->description,
            $line->quantity,
            $line->unit,
            $line->unit_price,
            $line->tax_rate,
            $line->line_total,
            $order->currency,
        ];
    }
}
