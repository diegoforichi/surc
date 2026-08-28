<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Subject extends Model implements HasMedia
{
    use BelongsToNetwork;
    use InteractsWithMedia;

    protected $fillable = [
        'network_id',
        'organization_id',
        'owner_party_id',
        'label_name',
        'code',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'owner_party_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }

    public function historyEntries(): HasMany
    {
        return $this->hasMany(SubjectHistoryEntry::class)
            ->where('network_id', $this->network_id)
            ->where('organization_id', $this->organization_id)
            ->orderByDesc('occurred_at');
    }
}
