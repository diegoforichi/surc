<?php

namespace App\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Resources\OrganizationResource;
use App\Support\Sales\OrganizationSalesSettings;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['settings']['sales'] = OrganizationSalesSettings::all($this->record);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! (auth()->user()?->isOrganizationAdmin() ?? false)) {
            unset($data['settings']);

            return $data;
        }

        $sales = data_get($data, 'settings.sales', []);
        unset($data['settings']);
        $current = $this->record->settings ?? [];
        $current = is_array($current) ? $current : [];
        $current['sales'] = array_replace(
            OrganizationSalesSettings::defaults(),
            is_array($sales) ? $sales : [],
        );
        $data['settings'] = $current;

        return $data;
    }
}
