<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SubjectResource;
use App\Models\SubjectHistoryEntry;
use App\Support\History\HistoryAccess;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingHistoryRemindersWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return HistoryAccess::canViewReminders(auth()->user());
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Próximos de mi clínica')
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('next_due_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(function (SubjectHistoryEntry $record): string {
                        if ($record->next_due_at?->isPast()) {
                            return 'danger';
                        }

                        if ($record->next_due_at?->lte(now()->addDays(7))) {
                            return 'warning';
                        }

                        return 'gray';
                    }),
                Tables\Columns\TextColumn::make('subject.label_name')
                    ->label(terminology('subject', 'Sujeto')),
                Tables\Columns\TextColumn::make('type.label')
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('summary')
                    ->label('Resumen')
                    ->limit(40),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Abrir ficha')
                    ->url(fn (SubjectHistoryEntry $record): string => SubjectResource::getUrl('history', ['record' => $record->subject_id])),
            ])
            ->paginated([5, 10]);
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        if ($user === null || ! HistoryAccess::canViewReminders($user)) {
            return SubjectHistoryEntry::query()->whereRaw('1 = 0');
        }

        return HistoryAccess::remindersQueryForUser($user)
            ->with(['subject', 'type'])
            ->whereDate('next_due_at', '<=', now()->addDays(30))
            ->orderBy('next_due_at');
    }
}
