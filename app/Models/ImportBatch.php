<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    use BelongsToNetwork;

    protected $fillable = [
        'network_id',
        'organization_id',
        'user_id',
        'target',
        'file_path',
        'mapping',
        'status',
        'rows_total',
        'rows_ok',
        'rows_failed',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'errors' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
