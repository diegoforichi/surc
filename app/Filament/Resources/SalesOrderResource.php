<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesOrderResource\Pages;
use App\Filament\Support\SalesOrderForm;
use App\Models\SalesOrder;
use App\Support\Sales\SalesAccess;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Operativa';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'Orden de venta';

    protected static ?string $pluralModelLabel = 'Órdenes de venta';

    public static function canViewAny(): bool
    {
        return SalesAccess::canManageOrders(auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof SalesOrder
            && SalesAccess::canEditOrder(auth()->user(), $record);
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof SalesOrder
            && SalesAccess::canViewOrder(auth()->user(), $record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return SalesAccess::scopeOrdersForUser(parent::getEloquentQuery(), auth()->user());
    }

    public static function form(Form $form): Form
    {
        return $form->schema(SalesOrderForm::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Número')
                    ->placeholder('Borrador')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SalesOrder::statusLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('subject_snapshot.label_name')
                    ->label(terminology('subject', 'Sujeto')),
                Tables\Columns\TextColumn::make('client_snapshot.display_name')
                    ->label(terminology('client', 'Cliente')),
                Tables\Columns\TextColumn::make('total')->label('Total'),
                Tables\Columns\TextColumn::make('currency')->label('Moneda'),
                Tables\Columns\TextColumn::make('issued_at')->label('Emitida')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesOrders::route('/'),
            'view' => Pages\ViewSalesOrder::route('/{record}'),
            'edit' => Pages\EditSalesOrder::route('/{record}/edit'),
        ];
    }
}
