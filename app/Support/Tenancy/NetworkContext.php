<?php

namespace App\Support\Tenancy;

use App\Models\Network;

class NetworkContext
{
    protected static ?Network $network = null;

    public static function set(?Network $network): void
    {
        static::$network = $network;
    }

    public static function get(): ?Network
    {
        return static::$network;
    }

    public static function id(): ?int
    {
        return static::$network?->id;
    }

    public static function clear(): void
    {
        static::$network = null;
    }
}
