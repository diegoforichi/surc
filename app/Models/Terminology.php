<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToNetwork;
use App\Support\TerminologyHelper;
use Illuminate\Database\Eloquent\Model;

class Terminology extends Model
{
    use BelongsToNetwork;

    protected $table = 'terminology';

    protected $fillable = [
        'network_id',
        'entity_key',
        'label',
        'label_plural',
        'description',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => TerminologyHelper::clearCache());
        static::deleted(fn () => TerminologyHelper::clearCache());
    }
}
