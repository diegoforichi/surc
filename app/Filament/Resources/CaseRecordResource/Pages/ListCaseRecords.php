<?php

namespace App\Filament\Resources\CaseRecordResource\Pages;

use App\Filament\Resources\CaseRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCaseRecords extends ListRecords
{
    protected static string $resource = CaseRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
