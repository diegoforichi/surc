<?php

namespace App\Filament\Resources;

use App\Enums\ActorCategory;
use App\Filament\Concerns\HasNetworkFormFields;
use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\PartyResource\Pages;
use App\Filament\Resources\PartyResource\RelationManagers\SubjectsRelationManager;
use App\Filament\Support\CustomFieldsSchema;
use App\Filament\Support\PublicImageUpload;
use App\Models\ActorType;
use App\Models\Party;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartyResource extends Resource
{
    use HasNetworkFormFields;
    use ScopesToUserNetwork;

    protected static ?string $model = Party::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Operativa';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Actor';

    protected static ?string $pluralModelLabel = 'Actores';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('cases.manage') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...self::networkIdFormFields(),
            Forms\Components\Select::make('actor_type_id')
                ->label('Tipo de actor')
                ->relationship(
                    'actorType',
                    'label',
                    fn (Builder $query) => self::scopeToUserNetwork($query),
                )
                ->required()
                ->searchable()
                ->preload()
                ->live(),
            self::organizationSelect(),
            Forms\Components\TextInput::make('display_name')->label('Nombre')->required(),
            Forms\Components\TextInput::make('document_id')->label('Documento'),
            Forms\Components\TextInput::make('email')->email(),
            Forms\Components\TextInput::make('phone')->label('Teléfono')->tel(),
            Forms\Components\TextInput::make('whatsapp')
                ->label('WhatsApp')
                ->helperText('Número internacional, sin espacios.'),
            Forms\Components\Textarea::make('bio')->label('Biografía')->columnSpanFull(),
            PublicImageUpload::make('photo_path', 'directory-photos', 'Foto'),
            Forms\Components\Select::make('user_id')
                ->label('Usuario vinculado')
                ->relationship(
                    'user',
                    'name',
                    fn (Builder $query, Get $get) => self::scopeToUserOrganization(
                        $query
                            ->where('is_active', true)
                            ->when(
                                $get('network_id') ?? auth()->user()?->network_id,
                                fn (Builder $userQuery, $networkId) => $userQuery->where('network_id', $networkId),
                            )
                    ),
                )
                ->searchable()
                ->preload()
                ->visible(function (Get $get): bool {
                    $actorTypeId = $get('actor_type_id');

                    if ($actorTypeId === null) {
                        return false;
                    }

                    $type = ActorType::query()->find($actorTypeId);

                    if ($type === null) {
                        return false;
                    }

                    return $type->is_user_linkable
                        && in_array($type->category, [
                            ActorCategory::Specialist,
                            ActorCategory::Professional,
                        ], true);
                }),
            Forms\Components\Select::make('default_workflow_template_id')
                ->label('Plantilla de flujo por defecto')
                ->relationship(
                    'defaultWorkflowTemplate',
                    'name',
                    fn (Builder $query, Get $get) => $query->when(
                        $get('network_id') ?? auth()->user()?->network_id,
                        fn (Builder $templateQuery, $networkId) => $templateQuery->where('network_id', $networkId),
                    ),
                )
                ->searchable()
                ->preload()
                ->visible(function (Get $get): bool {
                    $actorTypeId = $get('actor_type_id');

                    if ($actorTypeId === null) {
                        return false;
                    }

                    $type = ActorType::query()->find($actorTypeId);

                    if ($type === null) {
                        return false;
                    }

                    return in_array($type->category, [
                        ActorCategory::Specialist,
                        ActorCategory::Professional,
                    ], true);
                }),
            Forms\Components\Toggle::make('is_active')->label('Activo')->default(true),
            CustomFieldsSchema::section(
                'party',
                actorTypeGetter: fn ($get) => $get('actor_type_id'),
                networkIdGetter: fn ($get) => $get('network_id') ?? auth()->user()?->network_id,
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->label('Nombre')->searchable(),
                Tables\Columns\TextColumn::make('actorType.label')->label('Tipo'),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label(terminology('organization', 'Sede')),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('phone')->label('Teléfono'),
                Tables\Columns\TextColumn::make('user.name')->label('Usuario vinculado')->toggleable(),
                Tables\Columns\TextColumn::make('owned_subjects_count')
                    ->label(terminology_plural('subject', 'Sujetos'))
                    ->counts('ownedSubjects')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('actor_type_id')
                    ->label('Tipo de actor')
                    ->relationship(
                        'actorType',
                        'label',
                        fn (Builder $query) => self::scopeToUserNetwork($query),
                    ),
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label(terminology('organization', 'Sede'))
                    ->relationship(
                        'organization',
                        'name',
                        fn (Builder $query) => self::scopeOrganizationsForUser($query),
                    ),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParties::route('/'),
            'create' => Pages\CreateParty::route('/create'),
            'edit' => Pages\EditParty::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            SubjectsRelationManager::class,
        ];
    }
}
