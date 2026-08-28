<?php

namespace App\Models;

use App\Enums\ActorCategory;
use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActorType extends Model
{
    use BelongsToNetwork;

    protected $fillable = [
        'network_id',
        'key',
        'label',
        'label_plural',
        'category',
        'is_user_linkable',
        'show_in_directory',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => ActorCategory::class,
            'is_user_linkable' => 'boolean',
            'show_in_directory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }

    public function customFieldDefinitions(): HasMany
    {
        return $this->hasMany(CustomFieldDefinition::class);
    }
}
