<?php

namespace App\Actions\Templates;

use App\Domain\Templates\IndustryTemplateRegistry;
use App\Models\HistoryEntryType;
use App\Models\Network;
use App\Models\Terminology;
use App\Support\TerminologyHelper;

class SyncNetworkHistoryCatalog
{
    public function handle(Network $network): void
    {
        $pack = app(IndustryTemplateRegistry::class)->find($network->industry_template_key);

        foreach ($pack->terminology as $row) {
            $key = (string) ($row['entity_key'] ?? '');

            if ($key !== 'history' && $key !== 'history_entry') {
                continue;
            }

            Terminology::updateOrCreate(
                [
                    'network_id' => $network->id,
                    'entity_key' => $key,
                ],
                [
                    'label' => $row['label'] ?? $key,
                    'label_plural' => $row['label_plural'] ?? null,
                    'description' => $row['description'] ?? null,
                ],
            );
        }

        foreach ($pack->historyEntryTypes as $index => $type) {
            HistoryEntryType::updateOrCreate(
                [
                    'network_id' => $network->id,
                    'key' => $type['key'],
                ],
                [
                    'label' => $type['label'],
                    'description' => $type['description'] ?? null,
                    'field_schema' => $type['field_schema'] ?? null,
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }

        TerminologyHelper::clearCache($network->id);
    }
}
