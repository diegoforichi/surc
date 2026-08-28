<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\OrganizationResource\Pages;
use App\Filament\Support\PublicImageUpload;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrganizationResource extends Resource
{
    use ScopesToUserNetwork;

    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Red';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->can('organizations.manage') ?? false)
            || ($user?->isOrganizationAdmin() ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('organizations.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if ($user?->can('organizations.manage')) {
            return true;
        }

        return $user?->isOrganizationAdmin()
            && $user->organization_id === $record->id;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('organizations.manage') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return terminology('organization', 'Sedes');
    }

    public static function getModelLabel(): string
    {
        return terminology('organization', 'Sede');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('network_id')
                ->relationship('network', 'name')
                ->visible(fn () => auth()->user()?->is_platform_owner)
                ->required(),
            Forms\Components\Hidden::make('network_id')
                ->default(fn () => auth()->user()?->network_id)
                ->visible(fn () => ! auth()->user()?->is_platform_owner),
            Forms\Components\TextInput::make('name')->label('Nombre')->required(),
            Forms\Components\TextInput::make('slug')->label('Identificador URL')->required(),
            Forms\Components\TextInput::make('address')->label('Dirección'),
            Forms\Components\TextInput::make('phone')->label('Teléfono')->tel(),
            Forms\Components\TextInput::make('whatsapp')
                ->label('WhatsApp')
                ->helperText('Número internacional, sin espacios. Ej: 5491112345678'),
            Forms\Components\TextInput::make('email')->label('Correo')->email(),
            Forms\Components\TextInput::make('website')->label('Sitio web')->url(),
            Forms\Components\Textarea::make('description')->label('Descripción')->columnSpanFull(),
            PublicImageUpload::make('photo_path', 'directory-photos', 'Foto'),
            Forms\Components\Toggle::make('is_active')->label('Activa')->default(true),
            Forms\Components\Toggle::make('show_in_directory')->label('Mostrar en directorio')->default(true),
            Forms\Components\Toggle::make('history_enabled')
                ->label('Usar '.terminology('history', 'historial'))
                ->helperText('Solo aplica si la red tiene el módulo habilitado.')
                ->default(false),
            Forms\Components\Fieldset::make('Datos comerciales (órdenes de venta)')
                ->visible(fn (): bool => auth()->user()?->isOrganizationAdmin() ?? false)
                ->schema([
                    Forms\Components\TextInput::make('settings.sales.currency')
                        ->label('Moneda ISO')
                        ->maxLength(3)
                        ->default('UYU')
                        ->helperText('No usa la moneda de las señas. En Uruguay: UYU.'),
                    Forms\Components\TextInput::make('settings.sales.order_prefix')
                        ->label('Prefijo de orden')
                        ->default('OV')
                        ->maxLength(8),
                    Forms\Components\TextInput::make('settings.sales.issuer_name')
                        ->label('Razón social / emisor'),
                    Forms\Components\TextInput::make('settings.sales.issuer_document')
                        ->label('Documento del emisor'),
                    Forms\Components\TextInput::make('settings.sales.issuer_address')
                        ->label('Domicilio comercial')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('Identificador URL'),
                Tables\Columns\TextColumn::make('phone')->label('Teléfono'),
                Tables\Columns\IconColumn::make('is_active')->label('Activa')->boolean(),
                Tables\Columns\IconColumn::make('show_in_directory')->label('Directorio')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
