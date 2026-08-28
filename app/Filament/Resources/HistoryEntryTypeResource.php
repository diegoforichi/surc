<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasNetworkFormFields;
use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\HistoryEntryTypeResource\Pages;
use App\Models\HistoryEntryType;
use App\Support\History\HistoryFieldSchema;
use App\Support\Settings\NetworkSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HistoryEntryTypeResource extends Resource
{
    use HasNetworkFormFields;
    use ScopesToUserNetwork;

    protected static ?string $model = HistoryEntryType::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Tipo de registro';

    protected static ?string $pluralModelLabel = 'Tipos de registro';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! ($user?->can('config.manage') ?? false)) {
            return false;
        }

        return (bool) NetworkSettings::getForNetworkId($user?->network_id, 'modules.history_enabled', false)
            || ($user?->is_platform_owner ?? false);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...self::networkIdFormFields(),
            Forms\Components\TextInput::make('key')->label('Clave')->required()->maxLength(80),
            Forms\Components\TextInput::make('label')->label('Etiqueta')->required(),
            Forms\Components\Textarea::make('description')->label('Descripción'),
            Forms\Components\Repeater::make('field_schema')
                ->label('Campos del registro')
                ->helperText('Campos que se piden al cargar un registro de este tipo.')
                ->schema([
                    Forms\Components\TextInput::make('key')
                        ->label('Clave')
                        ->required()
                        ->maxLength(40)
                        ->helperText('Solo letras minúsculas, números y guión bajo.'),
                    Forms\Components\TextInput::make('label')->label('Etiqueta')->required(),
                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options(HistoryFieldSchema::typeLabels())
                        ->required()
                        ->live(),
                    Forms\Components\TagsInput::make('options')
                        ->label('Opciones')
                        ->visible(fn (Forms\Get $get): bool => in_array($get('type'), ['select', 'multiselect'], true)),
                    Forms\Components\Toggle::make('required')->label('Obligatorio')->default(false),
                ])
                ->columns(2)
                ->collapsible()
                ->default([])
                ->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')->label('Orden')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->label('Activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->label('Clave'),
                Tables\Columns\TextColumn::make('label')->label('Etiqueta')->searchable(),
                Tables\Columns\TextColumn::make('sort_order')->label('Orden')->sortable(),
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
            'index' => Pages\ListHistoryEntryTypes::route('/'),
            'create' => Pages\CreateHistoryEntryType::route('/create'),
            'edit' => Pages\EditHistoryEntryType::route('/{record}/edit'),
        ];
    }
}
