<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseHistoryShare extends Model
{
    protected $fillable = [
        'case_id',
        'subject_history_entry_id',
        'shared_by',
        'shared_at',
    ];

    protected function casts(): array
    {
        return [
            'shared_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(SubjectHistoryEntry::class, 'subject_history_entry_id');
    }
}
