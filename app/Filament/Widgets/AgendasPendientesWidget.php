<?php

namespace App\Filament\Widgets;

use App\Enums\AgendaStatus;
use App\Models\Agenda;
use App\Support\Cases\CaseOperationalAccess;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class AgendasPendientesWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return CaseOperationalAccess::canOperate();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Agendas pendientes')
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->placeholder('Sin título')
                    ->searchable(),
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label(terminology('organization', 'Sede')),
                Tables\Columns\TextColumn::make('specialist.display_name')
                    ->label(terminology('specialist', 'Especialista')),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (AgendaStatus $state): string => $state->label()),
                Tables\Columns\TextColumn::make('ready_cases')
                    ->label('Casos listos')
                    ->formatStateUsing(fn (Agenda $record): string => sprintf(
                        '%d de %d',
                        $record->casesReadyCount(),
                        $record->casesTotalCount(),
                    )),
            ])
            ->filters([
                Tables\Filters\Filter::make('scheduled_date')
                    ->label('Fecha')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde')
                            ->default(now()),
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
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Abrir agenda')
                    ->visible(fn (Agenda $record): bool => CaseOperationalAccess::canManageAgenda($record))
                    ->url(fn (Agenda $record): string => route('filament.admin.resources.agendas.edit', $record))
                    ->openUrlInNewTab(),
            ])
            ->paginated([5, 10]);
    }

    protected function getTableQuery(): Builder
    {
        $query = Agenda::query()
            ->whereIn('status', [AgendaStatus::Planned, AgendaStatus::Confirmed])
            ->orderBy('scheduled_date')
            ->orderBy('start_time');

        return CaseOperationalAccess::scopeAgendasForUser($query);
    }
}
