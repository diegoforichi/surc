<?php

namespace App\Filament\Resources\TerminologyResource\Pages;

use App\Filament\Resources\TerminologyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTerminology extends EditRecord
{
    protected static string $resource = TerminologyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
