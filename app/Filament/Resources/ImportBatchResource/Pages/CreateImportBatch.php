<?php

namespace App\Filament\Resources\ImportBatchResource\Pages;

use App\Filament\Resources\ImportBatchResource;
use App\Jobs\ProcessCsvImport;
use Filament\Resources\Pages\CreateRecord;

class CreateImportBatch extends CreateRecord
{
    protected static string $resource = ImportBatchResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        $data['network_id'] = $user?->is_platform_owner
            ? ($data['network_id'] ?? $user?->network_id)
            : $user?->network_id;
        $data['user_id'] = $user?->id;
        $data['status'] = 'pending';
        $data['rows_total'] = 0;
        $data['rows_ok'] = 0;
        $data['rows_failed'] = 0;

        $fixed = $user?->fixedOrganizationId();

        if ($fixed !== null) {
            $data['organization_id'] = $fixed;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        ProcessCsvImport::dispatch($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
