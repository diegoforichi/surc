<?php

namespace App\Filament\Support;

use App\Models\SalesCatalogItem;
use App\Models\SalesOrder;
use App\Support\Sales\SalesAccess;
use Filament\Forms;
use Filament\Forms\Set;

class SalesOrderForm
{
    /**
     * @return array<int, mixed>
     */
    public static function schema(bool $disabled = false): array
    {
        return [
            Forms\Components\Placeholder::make('number_display')
                ->label('Número')
                ->content(fn (?SalesOrder $record): string => $record?->number ?: 'Se asigna al emitir'),
            Forms\Components\Placeholder::make('status_display')
                ->label('Estado')
                ->content(fn (?SalesOrder $record): string => SalesOrder::statusLabels()[$record?->status ?? SalesOrder::STATUS_DRAFT] ?? '—'),
            Forms\Components\Placeholder::make('currency_display')
                ->label('Moneda')
                ->content(fn (?SalesOrder $record): string => $record?->currency ?: 'UYU'),
            Forms\Components\Placeholder::make('client_display')
                ->label(terminology('client', 'Cliente'))
                ->content(function (?SalesOrder $record): string {
                    $client = $record?->client_snapshot ?? [];

                    return trim(($client['display_name'] ?? '—').' '.(($client['document_id'] ?? '') ? '('.$client['document_id'].')' : ''));
                }),
            Forms\Components\Placeholder::make('subject_display')
                ->label(terminology('subject', 'Sujeto'))
                ->content(function (?SalesOrder $record): string {
                    $subject = $record?->subject_snapshot ?? [];

                    return $subject['label_name'] ?? '—';
                })
                ->columnSpanFull(),
            Forms\Components\Placeholder::make('deposit_display')
                ->label('Seña del caso (informativa)')
                ->content(function (?SalesOrder $record): string {
                    $deposit = $record?->deposit_reference;

                    if (! is_array($deposit) || ($deposit['amount'] ?? null) === null) {
                        return 'Sin seña confirmada en el caso de origen.';
                    }

                    return sprintf(
                        '%s %s — %s%s',
                        $deposit['currency'] ?? $record->currency,
                        number_format((float) $deposit['amount'], 2, ',', '.'),
                        $deposit['method'] ?? 'método no indicado',
                        filled($deposit['case_code'] ?? null) ? ' ('.$deposit['case_code'].')' : '',
                    );
                })
                ->columnSpanFull(),
            Forms\Components\Repeater::make('lines')
                ->label('Líneas')
                ->relationship()
                ->disabled($disabled)
                ->schema([
                    Forms\Components\Select::make('sales_catalog_item_id')
                        ->label('Catálogo')
                        ->options(fn (): array => SalesCatalogItem::query()
                            ->tap(fn ($query) => SalesAccess::scopeCatalogForUser($query, auth()->user(), true))
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (SalesCatalogItem $item): array => [
                                $item->id => $item->code.' — '.$item->description,
                            ])
                            ->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state): void {
                            if (! $state) {
                                $set('is_manual', true);

                                return;
                            }

                            $item = SalesCatalogItem::query()->find($state);

                            if ($item === null) {
                                return;
                            }

                            $set('code', $item->code);
                            $set('description', $item->description);
                            $set('unit', $item->unit);
                            $set('unit_price', $item->unit_price);
                            $set('tax_rate', $item->tax_rate);
                            $set('is_manual', false);
                        }),
                    Forms\Components\TextInput::make('code')->label('Código')->maxLength(40),
                    Forms\Components\TextInput::make('description')->label('Descripción')->required()->columnSpanFull(),
                    Forms\Components\TextInput::make('quantity')->label('Cantidad')->numeric()->required()->default(1),
                    Forms\Components\TextInput::make('unit')->label('Unidad')->required()->default('un'),
                    Forms\Components\TextInput::make('unit_price')->label('Precio')->numeric()->required()->default(0),
                    Forms\Components\TextInput::make('tax_rate')->label('Impuesto %')->numeric()->default(0),
                    Forms\Components\Hidden::make('is_manual')->default(true),
                    Forms\Components\Hidden::make('sort_order')->default(0),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->addActionLabel('Agregar línea')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('notes')
                ->label('Notas internas')
                ->disabled($disabled)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('erp_reference')
                ->label('Referencia ERP')
                ->helperText('Número que asigne el ERP después de facturar. No se calcula acá.')
                ->maxLength(80)
                ->visible(fn (?SalesOrder $record): bool => $record?->isLocked() ?? false),
        ];
    }
}
