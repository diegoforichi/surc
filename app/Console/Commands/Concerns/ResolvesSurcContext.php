<?php

namespace App\Console\Commands\Concerns;

use App\Models\Network;
use App\Models\Organization;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait ResolvesSurcContext
{
    protected function resolveNetwork(?string $identifier, bool $askWhenMissing = true): Network
    {
        if ($identifier !== null && $identifier !== '') {
            return Network::query()
                ->where('id', $identifier)
                ->orWhere('slug', $identifier)
                ->firstOrFail();
        }

        if (! $askWhenMissing) {
            throw (new ModelNotFoundException())->setModel(Network::class);
        }

        $networks = Network::query()->orderBy('name')->get();

        if ($networks->isEmpty()) {
            throw (new ModelNotFoundException())->setModel(Network::class);
        }

        $options = $networks
            ->mapWithKeys(fn (Network $network) => [$network->id => "{$network->name} ({$network->slug})"])
            ->all();

        $selectedId = $this->choice('Seleccione red', $options, array_key_first($options));

        return $networks->firstWhere('id', (int) $selectedId) ?? $networks->first();
    }

    protected function resolveOrganization(Network $network, ?string $identifier): ?Organization
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        return Organization::query()
            ->where('network_id', $network->id)
            ->where(function ($query) use ($identifier): void {
                $query->where('id', $identifier)
                    ->orWhere('slug', $identifier);
            })
            ->firstOrFail();
    }
}
