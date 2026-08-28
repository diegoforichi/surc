<?php

namespace App\Filament\Resources;

use App\Actions\Agendas\ConfirmAgenda;
use App\Enums\ActorCategory;
use App\Enums\AgendaStatus;
use App\Enums\CaseStatus;
use App\Filament\Concerns\AuthorizesCaseOperations;
use App\Filament\Concerns\HasNetworkFormFields;
use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\AgendaResource\Pages;
use App\Filament\Resources\AgendaResource\RelationManagers\CasesRelationManager;
use App\Models\Agenda;
use App\Support\Cases\CaseOperationalAccess;
use App\Support\Settings\NetworkSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AgendaResource extends Resource
{
    use AuthorizesCaseOperations;
    use HasNetworkFormFields;
    use ScopesToUserNetwork {
        getEloquentQuery as networkScopedQuery;
    }

    protected static ?string $model = Agenda::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Operativa';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return terminology('agenda', 'Agendas');
    }

    public static function getModelLabel(): string
    {
        return terminology('agenda', 'Agenda');
    }

    public static function getPluralModelLabel(): string
    {
        return terminology('agenda', 'Agendas');
    }

    public static function canViewAny(): bool
    {
        return CaseOperationalAccess::canOperate();
    }

    public static function getEloquentQuery(): Builder
    {
        return CaseOperationalAccess::scopeAgendasForUser(static::networkScopedQuery());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...self::networkIdFormFields(),
            self::organizationSelect()->required(),
            Forms\Components\Select::make('specialist_party_id')
                ->label(terminology('specialist', 'Especialista'))
                ->relationship(
                    'specialist',
                    'display_name',
                    fn (Builder $query) => self::relatedRecordsQuery($query)
                        ->whereHas('actorType', fn (Builder $typeQuery) => $typeQuery
                            ->whereIn('category', [
                                ActorCategory::Specialist,
                                ActorCategory::Professional,
                            ])),
                )
                ->helperText(fn (): string => sprintf(
                    '%s que atiende esta %s.',
                    terminology('specialist', 'Especialista'),
                    strtolower(terminology('agenda', 'Agenda')),
                ))
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('title')->label('Título'),
            Forms\Components\DatePicker::make('scheduled_date')
                ->label('Fecha')
                ->required()
                ->default(now()),
            Forms\Components\TimePicker::make('start_time')
                ->label('Hora de inicio')
                ->seconds(false)
                ->default(fn (): ?string => NetworkSettings::agendaDefaults()['start_time'] ?: null),
            Forms\Components\TextInput::make('slot_minutes')
                ->label('Minutos por paciente')
                ->numeric()
                ->default(fn (): int => NetworkSettings::agendaDefaults()['slot_minutes'])
                ->minValue(5)
                ->required(),
            Forms\Components\Select::make('status')
                ->label('Estado')
                ->options(collect(AgendaStatus::cases())->mapWithKeys(
                    fn (AgendaStatus $status) => [$status->value => $status->label()],
                ))
                ->default(AgendaStatus::Planned->value)
                ->required(),
            Forms\Components\Toggle::make('is_shared')
                ->label('Abierta a la red')
                ->helperText('Otras sedes pueden sumar sus derivaciones a esta visita. El historial de cada sede sigue siendo privado.')
                ->default(false),
            Forms\Components\Textarea::make('notes')
                ->label('Notas internas')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('instructions')
                ->label('Instrucciones para la constancia')
                ->helperText('Si se completa, reemplaza las instrucciones de la plantilla de flujo.')
                ->default(fn (): ?string => NetworkSettings::agendaDefaults()['instructions'])
                ->columnSpanFull(),
            Forms\Components\Textarea::make('consent_text')
                ->label('Consentimiento de esta agenda')
                ->helperText('Si se completa, reemplaza el consentimiento de la plantilla de flujo.')
                ->default(fn (): ?string => NetworkSettings::agendaDefaults()['consent_text'])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Hora')
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('slot_minutes')
                    ->label('Min/turno')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label(terminology('organization', 'Sede'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('specialist.display_name')
                    ->label(terminology('specialist', 'Especialista'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (AgendaStatus $state): string => $state->label()),
                Tables\Columns\TextColumn::make('is_shared')
                    ->label('Abierta a la red')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sí' : 'No')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('cases_count')
                    ->label(terminology('case', 'Casos'))
                    ->counts('cases')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ready_cases')
                    ->label('Casos listos')
                    ->formatStateUsing(fn (Agenda $record): string => sprintf(
                        '%d de %d',
                        $record->casesReadyCount(),
                        $record->casesTotalCount(),
                    )),
            ])
            ->defaultSort('scheduled_date')
            ->filters([
                Tables\Filters\Filter::make('scheduled_date')
                    ->label('Fecha')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('scheduled_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('scheduled_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Desde '.date('d/m/Y', strtotime($data['from']));
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Hasta '.date('d/m/Y', strtotime($data['until']));
                        }

                        return $indicators;
                    })
                    ->default(),
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label(terminology('organization', 'Sede'))
                    ->relationship(
                        'organization',
                        'name',
                        fn (Builder $query) => self::scopeOrganizationsForUser($query),
                    ),
                Tables\Filters\SelectFilter::make('specialist_party_id')
                    ->label(terminology('specialist', 'Especialista'))
                    ->relationship(
                        'specialist',
                        'display_name',
                        fn (Builder $query) => self::relatedRecordsQuery($query),
                    ),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(collect(AgendaStatus::cases())->mapWithKeys(
                        fn (AgendaStatus $status) => [$status->value => $status->label()],
                    )),
                Tables\Filters\TernaryFilter::make('is_shared')
                    ->label('Abierta a la red')
                    ->placeholder('Todas')
                    ->trueLabel('Solo abiertas')
                    ->falseLabel('Solo internas'),
            ])
            ->actions([
                Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Agenda $record): bool => CaseOperationalAccess::canManageAgenda($record)
                        && $record->status !== AgendaStatus::Cancelled
                        && $record->status !== AgendaStatus::Done)
                    ->action(function (Agenda $record): void {
                        $result = app(ConfirmAgenda::class)->handle($record);

                        if ($result['blocked']) {
                            Notification::make()
                                ->title('No se pudo confirmar la agenda')
                                ->body('Hay casos con flujo incompleto: '.implode(', ', $result['pending_titles']))
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($result['mode'] === 'warn' && $result['pending_titles'] !== []) {
                            Notification::make()
                                ->title(terminology('ux.agenda_confirmed_warn', 'Agenda confirmada con observaciones'))
                                ->body('Quedan casos sin completar el flujo: '.implode(', ', $result['pending_titles']))
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Agenda confirmada')
                            ->success()
                            ->send();
                    }),
                Action::make('mark_done')
                    ->label('Marcar realizada')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(function (Agenda $record): string {
                        $openCases = $record->cases()->where('status', CaseStatus::Open)->count();

                        if ($openCases > 0) {
                            return "Esta agenda tiene {$openCases} caso(s) sin cerrar. ¿Marcar como realizada de todos modos?";
                        }

                        return 'Se marcará la agenda como realizada.';
                    })
                    ->visible(fn (Agenda $record): bool => CaseOperationalAccess::canManageAgenda($record)
                        && $record->status !== AgendaStatus::Done
                        && $record->status !== AgendaStatus::Cancelled)
                    ->action(function (Agenda $record): void {
                        $record->update(['status' => AgendaStatus::Done]);

                        Notification::make()
                            ->title('Agenda marcada como realizada')
                            ->success()
                            ->send();
                    }),
                Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(function (Agenda $record): string {
                        $totalCases = $record->cases()->count();
                        $closedCases = $record->cases()->where('status', CaseStatus::Closed)->count();
                        $cancelledCases = $record->cases()->where('status', CaseStatus::Cancelled)->count();
                        $openCases = $record->cases()->where('status', CaseStatus::Open)->count();

                        return "Esta agenda tiene {$totalCases} caso(s): {$closedCases} cerrado(s), {$cancelledCases} cancelado(s) y {$openCases} abierto(s). ¿Confirmás cancelar la agenda?";
                    })
                    ->visible(fn (Agenda $record): bool => CaseOperationalAccess::canManageAgenda($record)
                        && $record->status !== AgendaStatus::Cancelled)
                    ->action(function (Agenda $record): void {
                        $record->update(['status' => AgendaStatus::Cancelled]);

                        Notification::make()
                            ->title('Agenda cancelada')
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Agenda $record): bool => CaseOperationalAccess::canManageAgenda($record)),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getRelations(): array
    {
        return [
            CasesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgendas::route('/'),
            'create' => Pages\CreateAgenda::route('/create'),
            'edit' => Pages\EditAgenda::route('/{record}/edit'),
        ];
    }

    protected static function scopesToOrganization(): bool
    {
        return false;
    }
}
