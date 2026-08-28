<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesCatalogItemResource\Pages;
use App\Models\HistoryEntryType;
use App\Models\SalesCatalogItem;
use App\Support\Sales\OrganizationSalesSettings;
use App\Support\Sales\SalesAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class SalesCatalogItemResource extends Resource
{
    protected static ?string $model = SalesCatalogItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Operativa';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'Ítem de catálogo';

    protected static ?string $pluralModelLabel = 'Catálogo de venta';

    public static function canViewAny(): bool
    {
        return SalesAccess::canManageCatalog(auth()->user());
    }

    public static function canCreate(): bool
    {
        return SalesAccess::canManageCatalog(auth()->user());
    }

    public static function canEdit($record): bool
    {
        return $record instanceof SalesCatalogItem
            && SalesAccess::canManageCatalog(auth()->user(), $record->organization);
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record);
    }

    public static function getEloquentQuery(): Builder
    {
        return SalesAccess::scopeCatalogForUser(parent::getEloquentQuery(), auth()->user());
    }

    public static function form(Form $form): Form
    {
        $user = auth()->user();
        $organization = $user?->organization;
        $currency = OrganizationSalesSettings::get($organization, 'currency', 'UYU');

        return $form->schema([
            Forms\Components\Hidden::make('network_id')
                ->default(fn () => $user?->network_id),
            Forms\Components\Hidden::make('organization_id')
                ->default(fn () => $user?->fixedOrganizationId()),
            Forms\Components\TextInput::make('code')
                ->label('Código ERP')
                ->required()
                ->maxLength(40)
                ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule) use ($user) {
                    return $rule->where('organization_id', $user?->fixedOrganizationId());
                }),
            Forms\Components\Select::make('kind')
                ->label('Tipo')
                ->options(SalesCatalogItem::kindLabels())
                ->required()
                ->default(SalesCatalogItem::KIND_SERVICE),
            Forms\Components\TextInput::make('description')
                ->label('Descripción')
                ->required()
                ->columnSpanFull(),
            Forms\Components\TextInput::make('unit')
                ->label('Unidad')
                ->required()
                ->default('un')
                ->maxLength(12),
            Forms\Components\TextInput::make('unit_price')
                ->label('Precio')
                ->numeric()
                ->required()
                ->default(0),
            Forms\Components\TextInput::make('tax_rate')
                ->label('Impuesto % (informativo)')
                ->numeric()
                ->default(0),
            Forms\Components\TextInput::make('currency')
                ->label('Moneda')
                ->maxLength(3)
                ->default($currency)
                ->disabled()
                ->dehydrated(),
            Forms\Components\Select::make('history_entry_type_id')
                ->label('Sugerir en tipo clínico')
                ->options(
                    HistoryEntryType::query()
                        ->where('network_id', $user?->network_id)
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->pluck('label', 'id'),
                )
                ->searchable()
                ->preload(),
            Forms\Components\Toggle::make('is_active')
                ->label('Activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('kind')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => SalesCatalogItem::kindLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('description')->label('Descripción')->searchable()->limit(50),
                Tables\Columns\TextColumn::make('unit_price')->label('Precio'),
                Tables\Columns\TextColumn::make('currency')->label('Moneda'),
                Tables\Columns\TextColumn::make('historyEntryType.label')->label('Tipo clínico')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesCatalogItems::route('/'),
            'create' => Pages\CreateSalesCatalogItem::route('/create'),
            'edit' => Pages\EditSalesCatalogItem::route('/{record}/edit'),
        ];
    }
}
