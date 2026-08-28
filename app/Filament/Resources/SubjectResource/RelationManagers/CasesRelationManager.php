<?php

namespace App\Filament\Resources\SubjectResource\RelationManagers;

use App\Models\CaseRecord;
use App\Support\Cases\CaseOperationalAccess;
use App\Support\Cases\CaseStatusDisplay;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CasesRelationManager extends RelationManager
{
    protected static string $relationship = 'cases';

    protected static ?string $title = 'Casos asociados';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return terminology('case', 'Casos').' asociados';
    }

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('cases.manage') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => CaseStatusDisplay::label($state)),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Hora estimada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('workspace')
                    ->label('Espacio de trabajo')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (CaseRecord $record): string => route('cases.show', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => CaseOperationalAccess::canManage()),
            ]);
    }
}
