<?php

namespace App\Support\Sales;

use App\Models\Organization;
use Illuminate\Support\Arr;

class OrganizationSalesSettings
{
    /**
     * @return array{
     *     currency: string,
     *     order_prefix: string,
     *     issuer_name: ?string,
     *     issuer_document: ?string,
     *     issuer_address: ?string
     * }
     */
    public static function defaults(): array
    {
        return [
            'currency' => 'UYU',
            'order_prefix' => 'OV',
            'issuer_name' => null,
            'issuer_document' => null,
            'issuer_address' => null,
        ];
    }

    /**
     * @return array{
     *     currency: string,
     *     order_prefix: string,
     *     issuer_name: ?string,
     *     issuer_document: ?string,
     *     issuer_address: ?string
     * }
     */
    public static function all(?Organization $organization): array
    {
        $saved = is_array($organization?->settings) ? ($organization->settings['sales'] ?? []) : [];
        $saved = is_array($saved) ? $saved : [];

        $merged = array_replace(self::defaults(), $saved);
        $merged['currency'] = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $merged['currency']) ?: 'UYU', 0, 3));
        $prefix = strtoupper(trim((string) $merged['order_prefix']));
        $merged['order_prefix'] = $prefix !== '' ? preg_replace('/[^A-Z0-9]/', '', $prefix) : 'OV';

        return $merged;
    }

    public static function get(?Organization $organization, string $key, mixed $default = null): mixed
    {
        return Arr::get(self::all($organization), $key, $default);
    }
}
