<?php

namespace App\Filament\Resources\PartyResource\RelationManagers;

use App\Enums\ActorCategory;
use App\Filament\Resources\SubjectResource;
use App\Models\Party;
use App\Models\Subject;
use App\Support\History\HistoryAccess;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'ownedSubjects';

    protected static ?string $title = 'Sujetos asignados';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return terminology('subject', 'Sujetos').' asignados';
    }

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Party) {
            return false;
        }

        return $ownerRecord->actorType?->category === ActorCategory::Client;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable(),
                Tables\Columns\TextColumn::make('label_name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label(terminology('organization', 'Sede')),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('label_name')
            ->actions([
                Tables\Actions\Action::make('history')
                    ->label(fn (): string => 'Ver '.strtolower((string) terminology('history', 'historial')))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->url(fn (Subject $record): string => SubjectResource::getUrl('view', ['record' => $record]))
                    ->visible(fn (Subject $record): bool => HistoryAccess::canViewSubject(auth()->user(), $record)
                        || SubjectResource::canView($record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('open')
                    ->label(fn (Subject $record): string => SubjectResource::canEdit($record) ? 'Editar' : 'Ver')
                    ->icon(fn (Subject $record): string => SubjectResource::canEdit($record) ? 'heroicon-o-pencil-square' : 'heroicon-o-eye')
                    ->url(fn (Subject $record): string => SubjectResource::getUrl(
                        SubjectResource::canEdit($record) ? 'edit' : 'view',
                        ['record' => $record],
                    ))
                    ->openUrlInNewTab(),
            ]);
    }
}
