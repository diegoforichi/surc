<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldDefinition extends Model
{
    use BelongsToNetwork;

    protected $fillable = [
        'network_id',
        'entity_type',
        'actor_type_id',
        'key',
        'label',
        'help_text',
        'field_type',
        'options',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'field_type' => CustomFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function actorType(): BelongsTo
    {
        return $this->belongsTo(ActorType::class);
    }
}
