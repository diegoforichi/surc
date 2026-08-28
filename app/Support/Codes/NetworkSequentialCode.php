<?php

namespace App\Support\Codes;

use Illuminate\Database\Eloquent\Model;

class NetworkSequentialCode
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function generate(
        string $modelClass,
        int $networkId,
        string $prefix,
        int $padLength = 4,
        string $column = 'code',
    ): string {
        $prefix = strtoupper(trim($prefix));

        $latestCode = $modelClass::query()
            ->where('network_id', $networkId)
            ->where($column, 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value($column);

        $sequence = 1;

        if (is_string($latestCode) && preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', $latestCode, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = sprintf('%s-%0'.$padLength.'d', $prefix, $sequence);
            $exists = $modelClass::query()
                ->where('network_id', $networkId)
                ->where($column, $candidate)
                ->exists();
            $sequence++;
        } while ($exists);

        return $candidate;
    }
}
