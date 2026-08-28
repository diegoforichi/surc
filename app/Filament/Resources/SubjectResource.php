<?php

namespace App\Filament\Resources;

use App\Actions\Parties\FindOrCreateClientParty;
use App\Enums\ActorCategory;
use App\Filament\Concerns\HasNetworkFormFields;
use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\SubjectResource\Pages;
use App\Filament\Resources\SubjectResource\RelationManagers\CasesRelationManager;
use App\Filament\Resources\SubjectResource\RelationManagers\HistoryEntriesRelationManager;
use App\Filament\Support\CustomFieldsSchema;
use App\Models\Subject;
use App\Support\History\HistoryAccess;
use App\Support\Settings\NetworkSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class SubjectResource extends Resource
{
    use HasNetworkFormFields;
    use ScopesToUserNetwork;

    protected static ?string $model = Subject::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Operativa';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return terminology('subject', 'Sujetos');
    }

    public static function getModelLabel(): string
    {
        return terminology('subject', 'Sujeto');
    }

    public static function getPluralModelLabel(): string
    {
        return terminology('subject', 'Sujetos');
    }

    public static function canViewAny(): bool
    {
        return self::subjectsEnabled()
            && HistoryAccess::canBrowseSubjects(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::subjectsEnabled()
            && (auth()->user()?->can('cases.manage') ?? false);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('cases.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('cases.manage') ?? false;
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        if (! self::subjectsEnabled() || ! $record instanceof Subject) {
            return false;
        }

        if ($user?->can('cases.manage')) {
            return true;
        }

        return HistoryAccess::canViewSubject($user, $record);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...self::networkIdFormFields(),
            self::organizationSelect(),
            self::ownerSelect(),
            self::otherSubjectsPlaceholder(),
            Forms\Components\TextInput::make('label_name')->label('Nombre')->required(),
            Forms\Components\TextInput::make('code')->label('Código'),
            Forms\Components\Toggle::make('is_active')->label('Activo')->default(true),
            CustomFieldsSchema::section(
                'subject',
                networkIdGetter: fn ($get) => $get('network_id') ?? auth()->user()?->network_id,
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label_name')->label('Nombre')->searchable(),
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label(terminology('organization', 'Sede')),
                Tables\Columns\TextColumn::make('owner.display_name')
                    ->label(terminology('client', 'Propietario'))
                    ->url(fn (Subject $record): ?string => static::ownerUrl($record))
                    ->color(fn (Subject $record): ?string => static::ownerUrl($record) ? 'primary' : null)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label(terminology('organization', 'Sede'))
                    ->relationship(
                        'organization',
                        'name',
                        fn (Builder $query) => self::scopeOrganizationsForUser($query),
                    ),
                Tables\Filters\SelectFilter::make('owner_party_id')
                    ->label(terminology('client', 'Propietario'))
                    ->relationship(
                        'owner',
                        'display_name',
                        fn (Builder $query) => self::relatedRecordsQuery($query),
                    ),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Subject $record): bool => static::canEdit($record)),
            ])
            ->recordUrl(fn (Subject $record): string => static::getUrl(
                static::canEdit($record) ? 'edit' : 'view',
                ['record' => $record],
            ))
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubjects::route('/'),
            'create' => Pages\CreateSubject::route('/create'),
            'view' => Pages\ViewSubject::route('/{record}'),
            'edit' => Pages\EditSubject::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            CasesRelationManager::class,
            HistoryEntriesRelationManager::class,
        ];
    }

    protected static function subjectsEnabled(): bool
    {
        return (bool) NetworkSettings::getForNetworkId(
            auth()->user()?->network_id,
            'entities.subjects_enabled',
            true,
        );
    }

    public static function ownerUrl(?Subject $record): ?string
    {
        if ($record?->owner_party_id === null || ! PartyResource::canViewAny()) {
            return null;
        }

        return PartyResource::getUrl('edit', ['record' => $record->owner_party_id]);
    }

    protected static function ownerSelect(): Forms\Components\Select
    {
        $clientLabel = terminology('client', 'Propietario');

        $select = Forms\Components\Select::make('owner_party_id')
            ->label($clientLabel)
            ->relationship(
                'owner',
                'display_name',
                fn (Builder $query) => self::relatedRecordsQuery($query)
                    ->whereHas(
                        'actorType',
                        fn (Builder $typeQuery) => $typeQuery->where('category', ActorCategory::Client),
                    ),
            )
            ->searchable()
            ->preload()
            ->helperText('Puede crear uno nuevo desde acá. Si el documento ya existe en esta '.strtolower((string) terminology('organization', 'sede')).', se reutiliza.');

        if (! (auth()->user()?->can('cases.manage') ?? false)) {
            return $select;
        }

        return $select
            ->createOptionForm([
                Forms\Components\TextInput::make('display_name')
                    ->label('Nombre')
                    ->required(),
                Forms\Components\TextInput::make('document_id')
                    ->label('Documento'),
                Forms\Components\TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel(),
                Forms\Components\TextInput::make('email')
                    ->email(),
                Forms\Components\TextInput::make('whatsapp')
                    ->label('WhatsApp'),
            ])
            ->createOptionUsing(function (array $data, Get $get, $livewire) use ($clientLabel): int {
                $user = auth()->user();
                $networkId = $user?->is_platform_owner
                    ? (int) ($get('network_id') ?: data_get($livewire, 'data.network_id'))
                    : $user?->network_id;
                $organizationId = $user?->fixedOrganizationId()
                    ?? $get('organization_id')
                    ?? data_get($livewire, 'data.organization_id');

                if ($networkId === null || $organizationId === null) {
                    throw ValidationException::withMessages([
                        'display_name' => 'Indique la '.strtolower((string) terminology('organization', 'sede')).' antes de crear el '.strtolower((string) $clientLabel).'.',
                    ]);
                }

                return app(FindOrCreateClientParty::class)->handle(
                    (int) $networkId,
                    (int) $organizationId,
                    $data,
                )->getKey();
            });
    }

    protected static function otherSubjectsPlaceholder(): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('owner_other_subjects')
            ->label(fn (): string => 'Otros '.strtolower((string) terminology_plural('subject', 'sujetos')).' de este '.strtolower((string) terminology('client', 'propietario')))
            ->content(function (?Model $record): HtmlString|string {
                if (! $record instanceof Subject || $record->owner_party_id === null) {
                    return '—';
                }

                $siblings = Subject::query()
                    ->where('owner_party_id', $record->owner_party_id)
                    ->where('id', '!=', $record->id)
                    ->orderBy('label_name')
                    ->get();

                if ($siblings->isEmpty()) {
                    return 'Ninguno más en esta '.strtolower((string) terminology('organization', 'sede')).'.';
                }

                $links = $siblings->map(function (Subject $sibling): string {
                    $url = e(static::getUrl(
                        static::canEdit($sibling) ? 'edit' : 'view',
                        ['record' => $sibling],
                    ));
                    $label = e($sibling->label_name.($sibling->code ? ' ('.$sibling->code.')' : ''));

                    return '<a class="text-primary-600 underline" href="'.$url.'">'.$label.'</a>';
                });

                return new HtmlString($links->implode('<br>'));
            })
            ->visible(fn (?Model $record): bool => $record instanceof Subject && $record->owner_party_id !== null)
            ->columnSpanFull();
    }
}
