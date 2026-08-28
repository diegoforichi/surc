<?php

namespace App\Support\Settings;

use App\Models\Network;
use App\Support\Tenancy\NetworkContext;
use Illuminate\Support\Arr;

class NetworkSettings
{
    public static function defaults(): array
    {
        return [
            'agenda' => [
                'confirm_mode' => 'warn',
                'case_ready_criteria' => 'confirmation_stage',
                'auto_done' => true,
                'defaults' => [
                    'slot_minutes' => 30,
                    'start_time' => '09:00',
                    'instructions' => null,
                    'consent_text' => null,
                ],
            ],
            'case' => [
                'finish_requires_diagnosis' => true,
                'finish_requires_technical' => true,
            ],
            'entities' => [
                'subjects_enabled' => true,
            ],
            'modules' => [
                'history_enabled' => false,
            ],
        ];
    }

    public static function all(?Network $network = null): array
    {
        $network ??= static::resolveNetwork();

        $saved = $network?->settings;
        $saved = is_array($saved) ? $saved : [];

        return array_replace_recursive(static::defaults(), $saved);
    }

    public static function get(string $key, mixed $default = null, ?Network $network = null): mixed
    {
        return Arr::get(static::all($network), $key, $default);
    }

    public static function allForNetworkId(?int $networkId): array
    {
        if ($networkId === null) {
            return static::defaults();
        }

        $network = Network::query()->find($networkId);

        return static::all($network);
    }

    public static function getForNetworkId(?int $networkId, string $key, mixed $default = null): mixed
    {
        return Arr::get(static::allForNetworkId($networkId), $key, $default);
    }

    /**
     * @return array{slot_minutes: int, start_time: ?string, instructions: ?string, consent_text: ?string}
     */
    public static function agendaDefaults(?Network $network = null): array
    {
        $defaults = [
            'slot_minutes' => 30,
            'start_time' => '09:00',
            'instructions' => null,
            'consent_text' => null,
        ];

        $saved = static::get('agenda.defaults', [], $network);

        if (! is_array($saved)) {
            return $defaults;
        }

        $merged = array_replace($defaults, $saved);
        $merged['slot_minutes'] = max(5, (int) $merged['slot_minutes']);

        return $merged;
    }

    protected static function resolveNetwork(): ?Network
    {
        $network = NetworkContext::get();

        if ($network !== null) {
            return $network;
        }

        $user = auth()->user();

        if ($user?->network) {
            return $user->network;
        }

        return null;
    }
}
