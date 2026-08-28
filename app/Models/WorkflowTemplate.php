<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTemplate extends Model
{
    use BelongsToNetwork;

    protected $fillable = [
        'network_id',
        'name',
        'is_default',
        'is_active',
        'instructions',
        'consent_text',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class)->orderBy('sort_order');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }
}
