<?php

namespace App\Models;

use App\Support\History\HistoryFieldSchema;
use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HistoryEntryType extends Model
{
    use BelongsToNetwork;

    protected $fillable = [
        'network_id',
        'key',
        'label',
        'description',
        'field_schema',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'field_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SubjectHistoryEntry::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $type): void {
            $type->field_schema = HistoryFieldSchema::normalize($type->field_schema);
        });
    }
}
