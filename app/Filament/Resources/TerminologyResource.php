<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasNetworkFormFields;
use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\TerminologyResource\Pages;
use App\Models\Terminology;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TerminologyResource extends Resource
{
    use HasNetworkFormFields;
    use ScopesToUserNetwork;

    protected static ?string $model = Terminology::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Terminología';

    protected static ?string $modelLabel = 'Término';

    protected static ?string $pluralModelLabel = 'Terminología';

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('config.manage') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...self::networkIdFormFields(),
            Forms\Components\TextInput::make('entity_key')->label('Clave de entidad')->required(),
            Forms\Components\TextInput::make('label')->label('Etiqueta')->required(),
            Forms\Components\TextInput::make('label_plural')
                ->label('Etiqueta plural')
                ->helperText('Opcional para claves ux.* y mensajes de interfaz.'),
            Forms\Components\Textarea::make('description')
                ->label('Descripción')
                ->helperText('Recomendado para documentar el uso de la clave.')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entity_key')->label('Clave')->searchable(),
                Tables\Columns\TextColumn::make('label')->label('Etiqueta')->searchable(),
                Tables\Columns\TextColumn::make('label_plural')->label('Plural'),
                Tables\Columns\TextColumn::make('description')->label('Descripción')->limit(50),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTerminologies::route('/'),
            'create' => Pages\CreateTerminology::route('/create'),
            'edit' => Pages\EditTerminology::route('/{record}/edit'),
        ];
    }
}
