<?php

namespace App\Support;

use App\Models\Terminology;
use App\Support\Tenancy\NetworkContext;
use Illuminate\Support\Facades\Cache;

class TerminologyHelper
{
    private const CACHEABLE_KEYS = [
        'organization',
        'subject',
        'client',
        'case',
        'specialist',
        'professional',
        'agenda',
        'history',
        'history_entry',
        'ux.status_attended',
        'ux.action_finish',
        'ux.consultation_title',
        'ux.agenda_confirmed_warn',
        'ux.case_diagnosis',
        'ux.case_treatment',
    ];

    public static function get(string $entityKey, ?string $fallback = null): string
    {
        return self::stored($entityKey, 'label') ?? $fallback ?? $entityKey;
    }

    public static function plural(string $entityKey, ?string $fallback = null): string
    {
        return self::stored($entityKey, 'label_plural')
            ?? self::get($entityKey, $fallback);
    }

    public static function clearCache(?int $networkId = null): void
    {
        $networkId ??= NetworkContext::id();

        if ($networkId === null) {
            return;
        }

        foreach (self::CACHEABLE_KEYS as $key) {
            Cache::forget("terminology.{$networkId}.{$key}");
            Cache::forget("terminology.{$networkId}.{$key}.plural");
        }
    }

    protected static function stored(string $entityKey, string $column): ?string
    {
        $networkId = NetworkContext::id();

        if ($networkId === null) {
            $network = auth()->user()?->network;
            if ($network === null) {
                return null;
            }

            NetworkContext::set($network);
            $networkId = $network->id;
        }

        $cacheKey = $column === 'label_plural'
            ? "terminology.{$networkId}.{$entityKey}.plural"
            : "terminology.{$networkId}.{$entityKey}";

        $stored = Cache::remember(
            $cacheKey,
            3600,
            fn () => Terminology::query()
                ->where('network_id', $networkId)
                ->where('entity_key', $entityKey)
                ->value($column)
        );

        return filled($stored) ? (string) $stored : null;
    }
}
