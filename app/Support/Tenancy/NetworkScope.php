<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class NetworkScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (NetworkContext::id() === null) {
            return;
        }

        $builder->where($model->getTable().'.network_id', NetworkContext::id());
    }
}
