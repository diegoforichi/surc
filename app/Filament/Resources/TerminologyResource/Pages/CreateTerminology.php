<?php

namespace App\Filament\Resources\TerminologyResource\Pages;

use App\Filament\Resources\TerminologyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTerminology extends CreateRecord
{
    protected static string $resource = TerminologyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TerminologyResource::assignNetworkId($data);
    }
}
