<?php

namespace App\Filament\Resources;

use App\Actions\Templates\ApplyIndustryTemplate;
use App\Domain\Templates\IndustryTemplateRegistry;
use App\Filament\Resources\NetworkResource\Pages;
use App\Filament\Support\NetworkInstitutionalForm;
use App\Models\Network;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NetworkResource extends Resource
{
    protected static ?string $model = Network::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Red';

    protected static ?string $pluralModelLabel = 'Redes';

    protected static ?string $navigationLabel = 'Redes';

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_platform_owner ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')
                ->label('Identificador URL')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            Forms\Components\Select::make('industry_template_key')
                ->label('Plantilla de industria')
                ->options(fn (): array => app(IndustryTemplateRegistry::class)->options())
                ->required(),
            Forms\Components\ColorPicker::make('primary_color')->label('Color principal')->default('#f59e0b'),
            Forms\Components\Toggle::make('is_active')->label('Activa')->default(true),
            ...NetworkInstitutionalForm::schema(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('Identificador URL'),
                Tables\Columns\TextColumn::make('industry_template_key')->label('Plantilla'),
                Tables\Columns\IconColumn::make('is_active')->label('Activa')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('applyTemplate')
                    ->label('Aplicar plantilla')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (Network $record): void {
                        app(ApplyIndustryTemplate::class)->handle($record);
                        Notification::make()->title('Plantilla aplicada')->success()->send();
                    }),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNetworks::route('/'),
            'create' => Pages\CreateNetwork::route('/create'),
            'edit' => Pages\EditNetwork::route('/{record}/edit'),
        ];
    }
}
