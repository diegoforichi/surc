<?php

namespace App\Filament\Resources;

use App\Enums\CustomFieldType;
use App\Filament\Concerns\HasNetworkFormFields;
use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\CustomFieldDefinitionResource\Pages;
use App\Models\CustomFieldDefinition;
use App\Support\Labels\OperationalStatusLabels;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomFieldDefinitionResource extends Resource
{
    use HasNetworkFormFields;
    use ScopesToUserNetwork;

    protected static ?string $model = CustomFieldDefinition::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Campo personalizado';

    protected static ?string $pluralModelLabel = 'Campos personalizados';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('config.manage') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...self::networkIdFormFields(),
            Forms\Components\Select::make('entity_type')
                ->label('Entidad')
                ->options([
                    'party' => 'Actor',
                    'subject' => terminology('subject', 'Sujeto'),
                    'case' => terminology('case', 'Caso'),
                ])
                ->required(),
            Forms\Components\Select::make('actor_type_id')
                ->label('Tipo de actor')
                ->relationship(
                    'actorType',
                    'label',
                    fn (Builder $query) => self::scopeToUserNetwork($query),
                )
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('key')->label('Clave')->required(),
            Forms\Components\TextInput::make('label')->label('Etiqueta')->required(),
            Forms\Components\Select::make('field_type')
                ->label('Tipo de campo')
                ->options(collect(CustomFieldType::cases())->mapWithKeys(
                    fn (CustomFieldType $t) => [$t->value => $t->label()],
                ))
                ->required()
                ->live(),
            Forms\Components\KeyValue::make('options')
                ->label('Opciones')
                ->visible(fn (Forms\Get $get): bool => in_array($get('field_type'), ['select', 'multiselect'])),
            Forms\Components\Toggle::make('is_required')->label('Obligatorio')->default(false),
            Forms\Components\TextInput::make('sort_order')->label('Orden')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->label('Activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Entidad')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OperationalStatusLabels::entityType($state)),
                Tables\Columns\TextColumn::make('key')->label('Clave')->searchable(),
                Tables\Columns\TextColumn::make('label')->label('Etiqueta')->searchable(),
                Tables\Columns\TextColumn::make('field_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state): string => $state instanceof CustomFieldType
                        ? $state->label()
                        : (CustomFieldType::tryFrom((string) $state)?->label() ?? (string) $state)),
                Tables\Columns\IconColumn::make('is_required')->label('Obligatorio')->boolean(),
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
            'index' => Pages\ListCustomFieldDefinitions::route('/'),
            'create' => Pages\CreateCustomFieldDefinition::route('/create'),
            'edit' => Pages\EditCustomFieldDefinition::route('/{record}/edit'),
        ];
    }
}
