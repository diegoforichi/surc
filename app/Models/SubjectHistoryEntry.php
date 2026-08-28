<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SubjectHistoryEntry extends Model implements HasMedia
{
    use BelongsToNetwork;
    use InteractsWithMedia;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINAL = 'final';

    protected $fillable = [
        'network_id',
        'organization_id',
        'subject_id',
        'history_entry_type_id',
        'occurred_at',
        'summary',
        'payload',
        'status',
        'author_user_id',
        'author_party_id',
        'source_case_id',
        'addendum_of_id',
        'finalized_by',
        'finalized_at',
        'next_due_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'finalized_at' => 'datetime',
            'next_due_at' => 'datetime',
        ];
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('local');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(HistoryEntryType::class, 'history_entry_type_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function sourceCase(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'source_case_id');
    }

    public function parentEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'addendum_of_id');
    }

    public function addenda(): HasMany
    {
        return $this->hasMany(self::class, 'addendum_of_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(CaseHistoryShare::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function activeSalesOrder(): ?SalesOrder
    {
        return $this->salesOrders
            ->first(fn (SalesOrder $order): bool => $order->isActive());
    }

    public static function nextDueFromPayload(mixed $payload): ?Carbon
    {
        $raw = is_array($payload) ? ($payload['next_due'] ?? null) : null;

        if (! is_string($raw) && ! $raw instanceof Carbon) {
            return null;
        }

        try {
            $due = $raw instanceof Carbon ? $raw : Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }

        return $due;
    }

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            $entry->next_due_at = self::nextDueFromPayload($entry->payload);
        });

        static::updating(function (self $entry): void {
            if (! $entry->getOriginal('status') || $entry->getOriginal('status') !== self::STATUS_FINAL) {
                return;
            }

            throw ValidationException::withMessages([
                'status' => 'Un registro finalizado no se puede editar. Use una adenda.',
            ]);
        });

        static::deleting(function (self $entry): void {
            if ($entry->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Un registro finalizado no se puede eliminar.',
                ]);
            }
        });
    }
}
