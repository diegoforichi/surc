<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesOrder extends Model
{
    use BelongsToNetwork;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_EXPORTED = 'exported';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'export_uid',
        'network_id',
        'organization_id',
        'subject_id',
        'subject_history_entry_id',
        'owner_party_id',
        'source_case_id',
        'number',
        'status',
        'currency',
        'subtotal',
        'tax_total',
        'total',
        'subject_snapshot',
        'client_snapshot',
        'organization_snapshot',
        'deposit_reference',
        'erp_reference',
        'notes',
        'created_by',
        'issued_by',
        'issued_at',
        'exported_by',
        'exported_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'subject_snapshot' => 'array',
            'client_snapshot' => 'array',
            'organization_snapshot' => 'array',
            'deposit_reference' => 'array',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'exported_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_ISSUED, self::STATUS_EXPORTED], true);
    }

    public function wasLocked(): bool
    {
        $status = $this->getOriginal('status') ?? $this->status;

        return in_array($status, [self::STATUS_ISSUED, self::STATUS_EXPORTED], true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_ISSUED, self::STATUS_EXPORTED], true);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function historyEntry(): BelongsTo
    {
        return $this->belongsTo(SubjectHistoryEntry::class, 'subject_history_entry_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'owner_party_id');
    }

    public function sourceCase(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'source_case_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function recalculateTotals(): void
    {
        $this->loadMissing('lines');
        $this->subtotal = $this->lines->sum(fn (SalesOrderLine $line): float => (float) $line->line_subtotal);
        $this->tax_total = $this->lines->sum(fn (SalesOrderLine $line): float => (float) $line->tax_amount);
        $this->total = $this->lines->sum(fn (SalesOrderLine $line): float => (float) $line->line_total);
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_ISSUED => 'Emitida',
            self::STATUS_EXPORTED => 'Exportada',
            self::STATUS_CANCELLED => 'Anulada',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->export_uid ??= (string) Str::uuid();
            $order->status ??= self::STATUS_DRAFT;
        });

        static::updating(function (self $order): void {
            if (! $order->wasLocked()) {
                return;
            }

            $allowed = [
                'status',
                'exported_at',
                'exported_by',
                'erp_reference',
                'updated_at',
            ];

            foreach ($order->getDirty() as $attribute => $value) {
                if (! in_array($attribute, $allowed, true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Una orden emitida no se puede modificar.',
                    ]);
                }
            }

            if ($order->isDirty('status')) {
                $from = $order->getOriginal('status');
                $to = $order->status;

                if ($from === self::STATUS_ISSUED && $to === self::STATUS_EXPORTED) {
                    return;
                }

                if ($from === self::STATUS_EXPORTED && $to === self::STATUS_EXPORTED) {
                    return;
                }

                throw ValidationException::withMessages([
                    'status' => 'Una orden emitida no se puede modificar.',
                ]);
            }
        });

        static::deleting(function (self $order): void {
            if ($order->isLocked()) {
                throw ValidationException::withMessages([
                    'status' => 'Una orden emitida no se puede eliminar.',
                ]);
            }
        });
    }
}
