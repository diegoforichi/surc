<?php

namespace App\Filament\Resources\SubjectResource\Pages;

use App\Filament\Resources\SubjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSubject extends ViewRecord
{
    protected static string $resource = SubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('owner')
                ->label(terminology('client', 'Propietario'))
                ->icon('heroicon-o-user')
                ->url(fn (): ?string => SubjectResource::ownerUrl($this->getRecord()))
                ->visible(fn (): bool => SubjectResource::ownerUrl($this->getRecord()) !== null)
                ->openUrlInNewTab(),
            Actions\EditAction::make()
                ->visible(fn (): bool => SubjectResource::canEdit($this->getRecord())),
        ];
    }
}
