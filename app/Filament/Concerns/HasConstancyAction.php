<?php

namespace App\Filament\Concerns;

use App\Models\CaseRecord;
use Filament\Actions\Action as PageAction;
use Filament\Tables\Actions\Action as TableAction;

trait HasConstancyAction
{
    public static function constancyTableAction(): TableAction
    {
        return TableAction::make('constancy')
            ->label('Constancia')
            ->icon('heroicon-o-ticket')
            ->url(fn (CaseRecord $record): string => route('cases.ticket', $record))
            ->openUrlInNewTab()
            ->visible(fn (CaseRecord $record): bool => $record->agenda_id !== null);
    }

    public static function constancyPageAction(CaseRecord $record): PageAction
    {
        return PageAction::make('constancy')
            ->label('Imprimir constancia')
            ->icon('heroicon-o-ticket')
            ->url(route('cases.ticket', $record))
            ->openUrlInNewTab()
            ->visible($record->agenda_id !== null);
    }
}
