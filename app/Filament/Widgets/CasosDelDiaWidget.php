<?php

namespace App\Filament\Widgets;

use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use App\Support\Cases\CaseOperationalAccess;
use App\Support\Cases\CaseStatusDisplay;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class CasosDelDiaWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return CaseOperationalAccess::canOperate();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Casos del día')
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Caso')
                    ->searchable(),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Hora')
                    ->dateTime('H:i'),
                Tables\Columns\TextColumn::make('agenda.specialist.display_name')
                    ->label(terminology('specialist', 'Especialista')),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (CaseStatus $state): string => CaseStatusDisplay::label($state)),
            ])
            ->actions([
                Tables\Actions\Action::make('workspace')
                    ->label('Espacio de trabajo')
                    ->url(fn (CaseRecord $record): string => route('cases.show', $record))
                    ->openUrlInNewTab(),
            ])
            ->paginated([5, 10]);
    }

    protected function getTableQuery(): Builder
    {
        $query = CaseRecord::query()
            ->where(function (Builder $builder): void {
                $builder
                    ->whereDate('scheduled_at', now()->toDateString())
                    ->orWhereHas(
                        'agenda',
                        fn (Builder $agendaQuery) => $agendaQuery->whereDate('scheduled_date', now()->toDateString()),
                    );
            })
            ->orderBy('scheduled_at');

        return CaseOperationalAccess::scopeCasesForUser($query);
    }
}
