<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesCatalogItem extends Model
{
    use BelongsToNetwork;

    public const KIND_PRODUCT = 'product';

    public const KIND_SERVICE = 'service';

    protected $fillable = [
        'network_id',
        'organization_id',
        'code',
        'kind',
        'description',
        'unit',
        'unit_price',
        'tax_rate',
        'currency',
        'history_entry_type_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function kindLabels(): array
    {
        return [
            self::KIND_SERVICE => 'Servicio',
            self::KIND_PRODUCT => 'Producto',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function historyEntryType(): BelongsTo
    {
        return $this->belongsTo(HistoryEntryType::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }
}
