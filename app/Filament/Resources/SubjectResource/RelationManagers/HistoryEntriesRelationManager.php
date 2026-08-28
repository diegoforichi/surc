<?php

namespace App\Filament\Resources\SubjectResource\RelationManagers;

use App\Actions\History\CreateHistoryAddendum;
use App\Actions\History\FinalizeHistoryEntry;
use App\Actions\History\PersistHistoryEntry;
use App\Actions\History\ShareHistoryEntry;
use App\Actions\Sales\PrepareSalesOrderFromHistory;
use App\Filament\Resources\SalesOrderResource;
use App\Filament\Support\HistoryEntryForm;
use App\Models\CaseRecord;
use App\Models\HistoryEntryType;
use App\Models\Subject;
use App\Models\SubjectHistoryEntry;
use App\Support\History\HistoryAccess;
use App\Support\Sales\SalesAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class HistoryEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'historyEntries';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return terminology('history', 'Historial');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Subject
            && HistoryAccess::canViewSubject(auth()->user(), $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        $owner = $this->getOwnerRecord();

        return ! ($owner instanceof Subject && HistoryAccess::canManageSubject(auth()->user(), $owner));
    }

    public function form(Form $form): Form
    {
        /** @var Subject $owner */
        $owner = $this->getOwnerRecord();

        return $form->schema(HistoryEntryForm::schema($owner));
    }

    public function table(Table $table): Table
    {
        /** @var Subject $owner */
        $owner = $this->getOwnerRecord();

        return $table
            ->modelLabel(terminology('history_entry', 'Registro'))
            ->pluralModelLabel(terminology('history_entry', 'Registros'))
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('network_id', $owner->network_id)
                ->where('organization_id', $owner->organization_id)
                ->whereNull('addendum_of_id')
                ->with(['shares.case', 'type', 'author', 'addenda', 'media']))
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('type.label')->label('Tipo'),
                Tables\Columns\TextColumn::make('summary')->label('Resumen')->limit(60),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === SubjectHistoryEntry::STATUS_FINAL ? 'Final' : 'Borrador'),
                Tables\Columns\TextColumn::make('author.name')->label('Autor'),
                Tables\Columns\TextColumn::make('shared_with')
                    ->label('Compartido con')
                    ->state(function (SubjectHistoryEntry $record): string {
                        $labels = $record->shares
                            ->map(fn ($share) => $share->case?->code ?: $share->case?->title)
                            ->filter()
                            ->unique()
                            ->values();

                        return $labels->isEmpty() ? '—' : $labels->implode(', ');
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('history_entry_type_id')
                    ->label('Tipo')
                    ->options(
                        HistoryEntryType::query()
                            ->where('network_id', $owner->network_id)
                            ->orderBy('sort_order')
                            ->pluck('label', 'id'),
                    ),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        SubjectHistoryEntry::STATUS_DRAFT => 'Borrador',
                        SubjectHistoryEntry::STATUS_FINAL => 'Final',
                    ]),
                Tables\Filters\Filter::make('occurred_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde'),
                        Forms\Components\DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $inner, $date) => $inner->whereDate('occurred_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $inner, $date) => $inner->whereDate('occurred_at', '<=', $date));
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('subjectPdf')
                    ->label('Descargar ficha PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (): string => route('history.subjects.pdf', $owner))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => HistoryAccess::canPrintSubject(auth()->user(), $owner)),
                Tables\Actions\CreateAction::make()
                    ->label('Nuevo registro')
                    ->modalHeading('Nuevo registro')
                    ->visible(fn (): bool => HistoryAccess::canManageSubject(auth()->user(), $owner))
                    ->using(fn (array $data): SubjectHistoryEntry => $this->persistEntry($data)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver registro')
                    ->modalHeading('Ver registro')
                    ->form(HistoryEntryForm::schema($owner, includeUploads: false)),
                Tables\Actions\EditAction::make()
                    ->label('Editar registro')
                    ->modalHeading('Editar registro')
                    ->visible(fn (SubjectHistoryEntry $record): bool => ! $record->isFinal()
                        && HistoryAccess::canManageSubject(auth()->user(), $owner))
                    ->using(fn (SubjectHistoryEntry $record, array $data): SubjectHistoryEntry => $this->persistEntry($data, $record)),
                Tables\Actions\Action::make('finalize')
                    ->label('Finalizar')
                    ->visible(fn (SubjectHistoryEntry $record): bool => ! $record->isFinal()
                        && HistoryAccess::canManageSubject(auth()->user(), $owner)
                        && (auth()->user()?->can('history.finalize') ?? false))
                    ->requiresConfirmation()
                    ->action(fn (SubjectHistoryEntry $record) => app(FinalizeHistoryEntry::class)->handle($record, auth()->user())),
                Tables\Actions\Action::make('addendum')
                    ->label('Adenda')
                    ->visible(fn (SubjectHistoryEntry $record): bool => $record->isFinal()
                        && HistoryAccess::canManageSubject(auth()->user(), $owner)
                        && (auth()->user()?->can('history.finalize') ?? false))
                    ->form([
                        Forms\Components\Textarea::make('summary')->label('Corrección')->required(),
                    ])
                    ->action(function (SubjectHistoryEntry $record, array $data): void {
                        app(CreateHistoryAddendum::class)->handle($record, auth()->user(), $data['summary']);
                    }),
                Tables\Actions\Action::make('pdf')
                    ->label('Descargar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (SubjectHistoryEntry $record): string => route('history.entries.pdf', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (SubjectHistoryEntry $record): bool => HistoryAccess::canPrintEntry(auth()->user(), $record)),
                Tables\Actions\Action::make('salesOrder')
                    ->label('Orden de venta')
                    ->visible(fn (SubjectHistoryEntry $record): bool => $record->isFinal()
                        && $record->addendum_of_id === null
                        && SalesAccess::canManageOrders(auth()->user(), $owner)
                        && HistoryAccess::canViewEntry(auth()->user(), $record))
                    ->action(function (SubjectHistoryEntry $record) {
                        try {
                            $order = app(PrepareSalesOrderFromHistory::class)->handle($record, auth()->user());
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title(collect($exception->errors())->flatten()->first() ?: 'No se pudo preparar la orden')
                                ->danger()
                                ->send();

                            return;
                        }

                        return redirect()->to(SalesOrderResource::getUrl(
                            $order->isDraft() ? 'edit' : 'view',
                            ['record' => $order],
                        ));
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar borrador')
                    ->modalHeading('Eliminar borrador')
                    ->visible(fn (SubjectHistoryEntry $record): bool => ! $record->isFinal()
                        && HistoryAccess::canManageSubject(auth()->user(), $owner)),
                Tables\Actions\Action::make('share')
                    ->label('Compartir con caso')
                    ->visible(fn (SubjectHistoryEntry $record): bool => $record->isFinal()
                        && HistoryAccess::canViewSubject(auth()->user(), $owner)
                        && (auth()->user()?->can('history.share') ?? false))
                    ->form([
                        Forms\Components\Select::make('case_id')
                            ->label(terminology('case', 'Caso'))
                            ->options(
                                CaseRecord::query()
                                    ->where('subject_id', $owner->id)
                                    ->orderByDesc('id')
                                    ->get()
                                    ->mapWithKeys(fn (CaseRecord $case) => [$case->id => $case->code ?: $case->title])
                            )
                            ->required(),
                    ])
                    ->action(function (SubjectHistoryEntry $record, array $data): void {
                        try {
                            $share = app(ShareHistoryEntry::class)->handle(
                                $record,
                                CaseRecord::query()->findOrFail($data['case_id']),
                                auth()->user(),
                            );
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title(collect($exception->errors())->flatten()->first() ?: 'No se pudo compartir')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($share->wasRecentlyCreated
                                ? 'Registro compartido con el caso'
                                : 'Ya estaba compartido con ese caso')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistEntry(array $data, ?SubjectHistoryEntry $record = null): SubjectHistoryEntry
    {
        /** @var Subject $owner */
        $owner = $this->getOwnerRecord();

        return app(PersistHistoryEntry::class)->handle($owner, auth()->user(), $data, $record);
    }
}
