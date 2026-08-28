<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk('local');
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
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

    public function addendumOf(): BelongsTo
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

    protected static function booted(): void
    {
        static::updating(function (self $entry): void {
            if (! $entry->getOriginal('status') || $entry->getOriginal('status') !== self::STATUS_FINAL) {
                return;
            }

            $allowed = ['updated_at'];
            $dirty = array_keys($entry->getDirty());

            if (array_diff($dirty, $allowed) !== []) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'Los registros finalizados son inmutables. Use una adenda.',
                ]);
            }
        });

        static::deleting(function (self $entry): void {
            if ($entry->isFinal()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'No se pueden eliminar registros finalizados.',
                ]);
            }
        });
    }
}
