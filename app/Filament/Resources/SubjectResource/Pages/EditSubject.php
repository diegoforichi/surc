<?php

namespace App\Filament\Resources\SubjectResource\Pages;

use App\Filament\Resources\SubjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubject extends EditRecord
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
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return SubjectResource::assignNetworkAndOrganization($data);
    }
}
