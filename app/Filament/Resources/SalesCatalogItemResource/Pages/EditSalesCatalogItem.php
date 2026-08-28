<?php

namespace App\Filament\Resources\SalesCatalogItemResource\Pages;

use App\Filament\Resources\SalesCatalogItemResource;
use App\Support\Sales\OrganizationSalesSettings;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesCatalogItem extends EditRecord
{
    protected static string $resource = SalesCatalogItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        $data['network_id'] = $user?->network_id;
        $data['organization_id'] = $user?->fixedOrganizationId();
        $data['currency'] = OrganizationSalesSettings::get($user?->organization, 'currency', 'UYU');

        return $data;
    }
}
