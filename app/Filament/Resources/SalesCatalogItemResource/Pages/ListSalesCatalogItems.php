<?php

namespace App\Filament\Resources\SalesCatalogItemResource\Pages;

use App\Filament\Resources\SalesCatalogItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesCatalogItems extends ListRecords
{
    protected static string $resource = SalesCatalogItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
