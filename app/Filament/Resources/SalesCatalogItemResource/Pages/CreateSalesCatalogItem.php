<?php

namespace App\Filament\Resources\SalesCatalogItemResource\Pages;

use App\Filament\Resources\SalesCatalogItemResource;
use App\Support\Sales\OrganizationSalesSettings;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesCatalogItem extends CreateRecord
{
    protected static string $resource = SalesCatalogItemResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['network_id'] = $user?->network_id;
        $data['organization_id'] = $user?->fixedOrganizationId();
        $data['currency'] = OrganizationSalesSettings::get($user?->organization, 'currency', 'UYU');

        return $data;
    }
}
