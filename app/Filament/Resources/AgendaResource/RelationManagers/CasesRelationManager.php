<?php

namespace App\Filament\Resources\AgendaResource\RelationManagers;

use App\Models\CaseRecord;
use App\Support\Cases\CaseOperationalAccess;
use App\Support\Cases\CaseStatusDisplay;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class CasesRelationManager extends RelationManager
{
    protected static string $relationship = 'cases';

    protected static ?string $title = 'Casos asignados';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return terminology('case', 'Casos').' asignados';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DateTimePicker::make('scheduled_at')
                ->label('Hora estimada')
                ->required()
                ->seconds(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Hora estimada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subject.label_name')
                    ->label(terminology('subject', 'Sujeto')),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Sede de origen'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => CaseStatusDisplay::label($state)),
            ])
            ->defaultSort('scheduled_at')
            ->headerActions([
                Tables\Actions\Action::make('assign')
                    ->label('Asignar '.terminology('case', 'caso'))
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => CaseOperationalAccess::canManageAgenda($this->getOwnerRecord()))
                    ->form([
                        Forms\Components\Select::make('case_id')
                            ->label(terminology('case', 'Caso'))
                            ->options(fn (): array => $this->availableCasesOptions())
                            ->searchable()
                            ->required(),
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Hora estimada')
                            ->required()
                            ->default(fn (): ?Carbon => $this->suggestedScheduledAt())
                            ->seconds(false),
                    ])
                    ->action(function (array $data): void {
                        $scheduledAt = $data['scheduled_at'] ?? $this->suggestedScheduledAt()?->format('Y-m-d H:i:s');

                        CaseOperationalAccess::scopeCasesForUser(
                            CaseRecord::query()->whereNull('agenda_id'),
                        )
                            ->whereKey($data['case_id'])
                            ->update([
                                'agenda_id' => $this->getOwnerRecord()->getKey(),
                                'scheduled_at' => $scheduledAt,
                            ]);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('constancy')
                    ->label('Constancia')
                    ->icon('heroicon-o-ticket')
                    ->url(fn (CaseRecord $record): string => route('cases.ticket', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('workspace')
                    ->label('Espacio de trabajo')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (CaseRecord $record): string => route('cases.show', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => CaseOperationalAccess::canManageAgenda($this->getOwnerRecord())),
                Tables\Actions\Action::make('detach')
                    ->label('Quitar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => CaseOperationalAccess::canManageAgenda($this->getOwnerRecord()))
                    ->action(fn (CaseRecord $record) => $record->update([
                        'agenda_id' => null,
                        'scheduled_at' => null,
                    ])),
            ]);
    }

    protected function suggestedScheduledAt(): ?Carbon
    {
        return $this->getOwnerRecord()->suggestedScheduledAtForNextCase();
    }

    /**
     * @return array<int|string, string>
     */
    public static function unassignedCaseOptions(): array
    {
        return CaseOperationalAccess::scopeCasesForUser(
            CaseRecord::query()
                ->with('subject')
                ->whereNull('agenda_id'),
        )
            ->orderBy('title')
            ->get()
            ->mapWithKeys(function (CaseRecord $case): array {
                $code = $case->code ?: 's/código';
                $title = $case->title ?: 'Sin título';
                $subject = $case->subject?->label_name ?: '—';

                return [$case->id => sprintf('%s — %s (%s)', $code, $title, $subject)];
            })
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    protected function availableCasesOptions(): array
    {
        return static::unassignedCaseOptions();
    }
}
