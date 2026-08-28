<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class SalesOrderLine extends Model
{
    protected $fillable = [
        'sales_order_id',
        'sales_catalog_item_id',
        'sort_order',
        'code',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'tax_rate',
        'line_subtotal',
        'tax_amount',
        'line_total',
        'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'is_manual' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(SalesCatalogItem::class, 'sales_catalog_item_id');
    }

    public function recalculateAmounts(): void
    {
        $quantity = round((float) $this->quantity, 2);
        $unitPrice = round((float) $this->unit_price, 2);
        $taxRate = round((float) $this->tax_rate, 2);
        $this->line_subtotal = round($quantity * $unitPrice, 2);
        $this->tax_amount = round($this->line_subtotal * $taxRate / 100, 2);
        $this->line_total = round((float) $this->line_subtotal + (float) $this->tax_amount, 2);
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            $line->recalculateAmounts();
        });

        static::updating(function (self $line): void {
            if ($line->order?->isLocked()) {
                throw ValidationException::withMessages([
                    'sales_order_id' => 'Una orden emitida no se puede modificar.',
                ]);
            }
        });

        static::deleting(function (self $line): void {
            if ($line->order?->isLocked()) {
                throw ValidationException::withMessages([
                    'sales_order_id' => 'Una orden emitida no se puede modificar.',
                ]);
            }
        });
    }
}
