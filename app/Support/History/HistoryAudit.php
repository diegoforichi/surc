<?php

namespace App\Support\History;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class HistoryAudit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(string $event, Model $performedOn, array $properties = []): void
    {
        activity('history')
            ->event($event)
            ->causedBy(Auth::user())
            ->performedOn($performedOn)
            ->withProperties($properties)
            ->log($event);
    }
}
