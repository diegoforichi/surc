<?php

namespace App\Models;

use App\Enums\RequirementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageRequirement extends Model
{
    protected $fillable = [
        'workflow_stage_id',
        'key',
        'label',
        'type',
        'is_mandatory',
        'config',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => RequirementType::class,
            'is_mandatory' => 'boolean',
            'config' => 'array',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }
}
