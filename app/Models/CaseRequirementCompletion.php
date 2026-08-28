<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseRequirementCompletion extends Model
{
    protected $fillable = [
        'case_id',
        'stage_requirement_id',
        'is_completed',
        'value',
        'completed_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'value' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(StageRequirement::class, 'stage_requirement_id');
    }
}
