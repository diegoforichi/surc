<?php

namespace App\Filament\Resources\HistoryEntryTypeResource\Pages;

use App\Filament\Resources\HistoryEntryTypeResource;
use App\Support\History\HistoryFieldSchema;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHistoryEntryType extends EditRecord
{
    protected static string $resource = HistoryEntryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['field_schema'] = HistoryFieldSchema::normalize($data['field_schema'] ?? []);

        return $data;
    }
}
