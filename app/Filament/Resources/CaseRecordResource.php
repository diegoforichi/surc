<?php

namespace App\Filament\Resources;

use App\Enums\CaseStatus;
use App\Enums\AgendaStatus;
use App\Actions\Agendas\RecalculateAgendaStatus;
use App\Filament\Concerns\AuthorizesCaseOperations;
use App\Filament\Concerns\HasConstancyAction;
use App\Filament\Concerns\HasNetworkFormFields;
use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\CaseRecordResource\Pages;
use App\Filament\Support\CustomFieldsSchema;
use App\Models\Agenda;
use App\Models\CaseEvent;
use App\Models\CaseRecord;
use App\Models\Subject;
use App\Support\Cases\CaseOperationalAccess;
use App\Support\Cases\CaseStatusDisplay;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CaseRecordResource extends Resource
{
    use AuthorizesCaseOperations;
    use HasConstancyAction;
    use HasNetworkFormFields;
    use ScopesToUserNetwork {
        getEloquentQuery as networkScopedQuery;
    }

    protected static ?string $model = CaseRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Operativa';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return terminology('case', 'Casos');
    }

    public static function getModelLabel(): string
    {
        return terminology('case', 'Caso');
    }

    public static function getPluralModelLabel(): string
    {
        return terminology('case', 'Casos');
    }

    public static function canViewAny(): bool
    {
        return CaseOperationalAccess::canOperate();
    }

    public static function getEloquentQuery(): Builder
    {
        return CaseOperationalAccess::scopeCasesForUser(static::networkScopedQuery())
            ->with(['agenda.organization', 'agenda.specialist']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...self::networkIdFormFields(),
            self::organizationSelect()->required(),
            Forms\Components\Select::make('subject_id')
                ->label(terminology('subject', 'Sujeto'))
                ->relationship(
                    'subject',
                    'label_name',
                    fn (Builder $query) => self::relatedRecordsQuery($query),
                )
                ->getOptionLabelFromRecordUsing(fn (Subject $record): string => $record->code
                    ? "{$record->label_name} ({$record->code})"
                    : $record->label_name)
                ->searchable(['label_name', 'code'])
                ->preload(),
            Forms\Components\Select::make('workflow_template_id')
                ->label('Plantilla de flujo')
                ->relationship(
                    'workflowTemplate',
                    'name',
                    fn (Builder $query) => self::scopeToUserNetwork($query),
                )
                ->helperText('Se define al crear el caso y luego queda fija para mantener consistencia del flujo.')
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->dehydrated()
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('code')->label('Código'),
            Forms\Components\TextInput::make('title')->label('Título')->required(),
            Forms\Components\Select::make('status')
                ->label('Estado')
                ->options(collect(CaseStatus::cases())->mapWithKeys(
                    fn (CaseStatus $s) => [$s->value => $s->label()],
                ))
                ->default(CaseStatus::Open->value)
                ->required(),
            Forms\Components\Textarea::make('summary')->label('Resumen')->columnSpanFull(),
            Forms\Components\Select::make('agenda_id')
                ->label(terminology('agenda', 'Agenda'))
                ->relationship(
                    'agenda',
                    'title',
                    fn (Builder $query) => CaseOperationalAccess::scopeAgendasForUser(self::scopeToUserNetwork($query))
                        ->whereIn('status', [AgendaStatus::Planned, AgendaStatus::Confirmed])
                        ->orderByDesc('scheduled_date'),
                )
                ->helperText('Visita donde se atenderá. Puede ser en otra sede si la agenda está abierta a la red.')
                ->live()
                ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                    if (blank($state)) {
                        return;
                    }

                    $agenda = Agenda::query()
                        ->with('specialist')
                        ->find($state);

                    if ($agenda === null) {
                        return;
                    }

                    $defaultTemplateId = $agenda->specialist?->default_workflow_template_id;

                    if (blank($get('workflow_template_id')) && $defaultTemplateId !== null) {
                        $set('workflow_template_id', $defaultTemplateId);
                    }

                    if (blank($get('scheduled_at'))) {
                        $set('scheduled_at', $agenda->suggestedScheduledAtForNextCase()?->format('Y-m-d H:i:s'));
                    }
                })
                ->getOptionLabelFromRecordUsing(fn (Agenda $record): string => $record->optionLabel())
                ->searchable()
                ->preload(),
            Forms\Components\DateTimePicker::make('scheduled_at')
                ->label('Hora estimada')
                ->seconds(false),
            CustomFieldsSchema::section(
                'case',
                networkIdGetter: fn ($get) => $get('network_id') ?? Auth::user()?->network_id,
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('title')->label('Título')->searchable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label(terminology('organization', 'Sede')),
                Tables\Columns\TextColumn::make('host_organization')
                    ->label('Se atiende en')
                    ->state(fn (CaseRecord $record): ?string => static::hostedOrganizationName($record))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Hora estimada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('agenda.scheduled_date')
                    ->label(terminology('agenda', 'Agenda'))
                    ->date('d/m/Y')
                    ->description(fn (CaseRecord $record): ?string => $record->agenda?->specialist?->display_name),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (CaseStatus $state): string => CaseStatusDisplay::label($state)),
                Tables\Columns\TextColumn::make('currentStage.label')->label('Etapa actual'),
            ])
            ->actions([
                self::constancyTableAction(),
                Tables\Actions\Action::make('workspace')
                    ->label('Espacio de trabajo')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (CaseRecord $record): string => route('cases.show', $record))
                    ->openUrlInNewTab(),
                Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CaseRecord $record): bool => CaseOperationalAccess::canManageCase($record) && $record->status === CaseStatus::Open)
                    ->action(function (CaseRecord $record): void {
                        $record->update([
                            'status' => CaseStatus::Cancelled,
                            'closed_at' => now(),
                            'closed_by' => Auth::id(),
                            'current_stage_id' => null,
                        ]);

                        CaseEvent::create([
                            'case_id' => $record->id,
                            'type' => 'case_cancelled',
                            'description' => 'Caso cancelado',
                            'created_by' => Auth::id(),
                            'created_at' => now(),
                        ]);

                        if ($record->agenda) {
                            app(RecalculateAgendaStatus::class)->handle($record->agenda);
                        }

                        Notification::make()
                            ->title('Caso cancelado')
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (CaseRecord $record): bool => CaseOperationalAccess::canManageCase($record)),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCaseRecords::route('/'),
            'create' => Pages\CreateCaseRecord::route('/create'),
            'edit' => Pages\EditCaseRecord::route('/{record}/edit'),
        ];
    }

    protected static function hostedOrganizationName(CaseRecord $record): ?string
    {
        $agendaOrganization = $record->agenda?->organization;

        if ($agendaOrganization === null || $agendaOrganization->id === $record->organization_id) {
            return null;
        }

        return $agendaOrganization->name;
    }

    protected static function scopesToOrganization(): bool
    {
        return false;
    }
}
