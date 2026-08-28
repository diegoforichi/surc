<?php

namespace App\Filament\Resources;

use App\Enums\ActorCategory;
use App\Filament\Concerns\HasNetworkFormFields;
use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\ActorTypeResource\Pages;
use App\Models\ActorType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActorTypeResource extends Resource
{
    use HasNetworkFormFields;
    use ScopesToUserNetwork;

    protected static ?string $model = ActorType::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Tipo de actor';

    protected static ?string $pluralModelLabel = 'Tipos de actor';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('config.manage') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...self::networkIdFormFields(),
            Forms\Components\TextInput::make('key')->label('Clave')->required()->maxLength(255),
            Forms\Components\TextInput::make('label')->label('Etiqueta')->required(),
            Forms\Components\TextInput::make('label_plural')->label('Etiqueta plural')->required(),
            Forms\Components\Select::make('category')
                ->label('Categoría')
                ->options(collect(ActorCategory::cases())->mapWithKeys(
                    fn (ActorCategory $c) => [$c->value => $c->label()],
                ))
                ->required(),
            Forms\Components\Toggle::make('is_user_linkable')->label('Vinculable a usuario')->default(false),
            Forms\Components\Toggle::make('show_in_directory')->label('Mostrar en directorio')->default(true),
            Forms\Components\TextInput::make('sort_order')->label('Orden')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->label('Activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->label('Clave')->searchable(),
                Tables\Columns\TextColumn::make('label')->label('Etiqueta')->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoría')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof ActorCategory
                        ? $state->label()
                        : (ActorCategory::tryFrom((string) $state)?->label() ?? (string) $state)),
                Tables\Columns\IconColumn::make('is_user_linkable')->label('Usuario')->boolean(),
                Tables\Columns\IconColumn::make('show_in_directory')->label('Directorio')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Orden')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActorTypes::route('/'),
            'create' => Pages\CreateActorType::route('/create'),
            'edit' => Pages\EditActorType::route('/{record}/edit'),
        ];
    }
}
