<?php

use App\Support\TerminologyHelper;

if (! function_exists('terminology')) {
    function terminology(string $entityKey, ?string $fallback = null): string
    {
        return TerminologyHelper::get($entityKey, $fallback);
    }
}

if (! function_exists('terminology_plural')) {
    function terminology_plural(string $entityKey, ?string $fallback = null): string
    {
        return TerminologyHelper::plural($entityKey, $fallback);
    }
}
