<?php

namespace App\Support\Tenancy;

use App\Models\Network;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToNetwork
{
    public static function bootBelongsToNetwork(): void
    {
        static::addGlobalScope(new NetworkScope);

        static::creating(function ($model): void {
            if ($model->network_id === null && NetworkContext::id() !== null) {
                $model->network_id = NetworkContext::id();
            }
        });
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class);
    }
}
