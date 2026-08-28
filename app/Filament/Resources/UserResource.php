<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasNetworkFormFields;
use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\Auth\AssignableRoles;
use App\Support\Auth\RoleLabels;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    use HasNetworkFormFields;
    use ScopesToUserNetwork;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Red';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (! ($user?->can('users.manage') ?? false)) {
            return false;
        }

        if ($record->is_platform_owner && ! $user->is_platform_owner) {
            return false;
        }

        return true;
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record) && ! $record->is_platform_owner;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...self::networkIdFormFields(),
            self::organizationSelect(),
            Forms\Components\TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->email()->required()->maxLength(255),
            Forms\Components\TextInput::make('password')
                ->label('Contraseña')
                ->password()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->maxLength(255),
            Forms\Components\Toggle::make('is_active')->label('Activo')->default(true),
            Forms\Components\Select::make('roles')
                ->label('Roles')
                ->multiple()
                ->relationship(
                    'roles',
                    'name',
                    fn (Builder $query) => $query->whereIn('name', AssignableRoles::names(auth()->user())),
                )
                ->getOptionLabelFromRecordUsing(fn ($record): string => RoleLabels::label((string) $record->name))
                ->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label(terminology('organization', 'Sede')),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RoleLabels::label($state)),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
