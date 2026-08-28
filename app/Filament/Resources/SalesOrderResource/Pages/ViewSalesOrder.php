<?php

namespace App\Filament\Resources\SalesOrderResource\Pages;

use App\Filament\Resources\SalesOrderResource;
use App\Filament\Support\SalesOrderForm;
use App\Models\SalesOrder;
use App\Support\Sales\SalesAccess;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesOrder extends ViewRecord
{
    protected static string $resource = SalesOrderResource::class;

    public function form(Form $form): Form
    {
        return $form->schema(SalesOrderForm::schema(disabled: true));
    }

    protected function getHeaderActions(): array
    {
        /** @var SalesOrder $record */
        $record = $this->getRecord();
        $user = auth()->user();

        return [
            Actions\EditAction::make()
                ->visible(fn (): bool => SalesAccess::canEditOrder($user, $record)),
            Actions\Action::make('pdf')
                ->label('Descargar PDF')
                ->url(fn (): string => route('sales.orders.pdf', $record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $record->isLocked() && SalesAccess::canExportOrders($user, $record->organization)),
            Actions\Action::make('csv')
                ->label('Descargar CSV')
                ->url(fn (): string => route('sales.orders.csv', $record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $record->isLocked() && SalesAccess::canExportOrders($user, $record->organization)),
        ];
    }
}
