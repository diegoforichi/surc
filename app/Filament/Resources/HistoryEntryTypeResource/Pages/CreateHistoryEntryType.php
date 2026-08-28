<?php

namespace App\Filament\Resources\HistoryEntryTypeResource\Pages;

use App\Filament\Resources\HistoryEntryTypeResource;
use App\Support\History\HistoryFieldSchema;
use Filament\Resources\Pages\CreateRecord;

class CreateHistoryEntryType extends CreateRecord
{
    protected static string $resource = HistoryEntryTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = HistoryEntryTypeResource::assignNetworkId($data);
        $data['field_schema'] = HistoryFieldSchema::normalize($data['field_schema'] ?? []);

        return $data;
    }
}
