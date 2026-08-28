<?php

namespace App\Filament\Resources\SalesOrderResource\Pages;

use App\Actions\Sales\CancelSalesOrder;
use App\Actions\Sales\IssueSalesOrder;
use App\Filament\Resources\SalesOrderResource;
use App\Filament\Support\SalesOrderForm;
use App\Models\SalesOrder;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditSalesOrder extends EditRecord
{
    protected static string $resource = SalesOrderResource::class;

    public function form(Form $form): Form
    {
        return $form->schema(SalesOrderForm::schema(disabled: false));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('issue')
                ->label('Emitir')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->isDraft())
                ->action(function (): void {
                    try {
                        $order = app(IssueSalesOrder::class)->handle($this->getRecord(), auth()->user());
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title(collect($exception->errors())->flatten()->first() ?: 'No se pudo emitir')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()->title('Orden emitida: '.$order->number)->success()->send();
                    $this->redirect(SalesOrderResource::getUrl('view', ['record' => $order]));
                }),
            Actions\Action::make('cancel')
                ->label('Anular borrador')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->isDraft())
                ->action(function (): void {
                    app(CancelSalesOrder::class)->handle($this->getRecord(), auth()->user());
                    Notification::make()->title('Orden anulada')->success()->send();
                    $this->redirect(SalesOrderResource::getUrl());
                }),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $record->recalculateTotals();
        $record->save();
    }

    public function getRecord(): SalesOrder
    {
        /** @var SalesOrder $record */
        $record = parent::getRecord();

        return $record;
    }
}
