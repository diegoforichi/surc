<?php

namespace App\Livewire;

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
use App\Support\History\HistoryFieldSchema;
use App\Support\Sales\SalesAccess;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class SubjectHistoryTimeline extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public Subject $subject;

    public string $search = '';

    public ?int $typeFilter = null;

    public ?string $statusFilter = null;

    public ?string $from = null;

    public ?string $until = null;

    public function mount(Subject $subject): void
    {
        abort_unless(HistoryAccess::canViewSubject(auth()->user(), $subject), 403);
        $this->subject = $subject;
    }

    /**
     * @return Collection<int, HistoryEntryType>
     */
    public function quickTypes(): Collection
    {
        return HistoryEntryType::query()
            ->where('network_id', $this->subject->network_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->limit(6)
            ->get();
    }

    /**
     * @return Collection<int, SubjectHistoryEntry>
     */
    public function entries(): Collection
    {
        $query = HistoryAccess::entriesQueryForSubject($this->subject)
            ->whereNull('addendum_of_id')
            ->with(['type', 'author', 'addenda.author', 'media', 'shares.case', 'salesOrders'])
            ->orderByDesc('occurred_at');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('summary', 'like', $term)
                    ->orWhereHas('type', fn ($type) => $type->where('label', 'like', $term));
            });
        }

        if ($this->typeFilter) {
            $query->where('history_entry_type_id', $this->typeFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->from) {
            $query->whereDate('occurred_at', '>=', $this->from);
        }

        if ($this->until) {
            $query->whereDate('occurred_at', '<=', $this->until);
        }

        return $query->get();
    }

    public function startType(int $typeId): void
    {
        $this->mountAction('create', ['typeId' => $typeId]);
    }

    public function createAction(): Action
    {
        return Action::make('create')
            ->label('+ '.terminology('history_entry', 'Registro'))
            ->visible(fn (): bool => HistoryAccess::canManageSubject(auth()->user(), $this->subject))
            ->fillForm(function (array $arguments): array {
                $typeId = isset($arguments['typeId']) ? (int) $arguments['typeId'] : null;

                return [
                    'history_entry_type_id' => $typeId,
                    'occurred_at' => now(),
                    'payload' => HistoryEntryForm::reusablePayload($this->subject, $typeId),
                ];
            })
            ->form(HistoryEntryForm::schema($this->subject))
            ->action(function (array $data): void {
                app(PersistHistoryEntry::class)->handle($this->subject, auth()->user(), $data);
                Notification::make()->title('Registro creado')->success()->send();
            });
    }

    public function editAction(): Action
    {
        return Action::make('edit')
            ->label('Editar')
            ->fillForm(function (array $arguments): array {
                $entry = $this->entry((int) $arguments['entry']);

                return [
                    'history_entry_type_id' => $entry->history_entry_type_id,
                    'occurred_at' => $entry->occurred_at,
                    'summary' => $entry->summary,
                    'payload' => $entry->payload ?? [],
                ];
            })
            ->form(HistoryEntryForm::schema($this->subject))
            ->action(function (array $arguments, array $data): void {
                $entry = $this->entry((int) $arguments['entry']);
                app(PersistHistoryEntry::class)->handle($this->subject, auth()->user(), $data, $entry);
                Notification::make()->title('Registro actualizado')->success()->send();
            });
    }

    public function finalizeAction(): Action
    {
        return Action::make('finalize')
            ->label('Finalizar')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                try {
                    app(FinalizeHistoryEntry::class)->handle($this->entry((int) $arguments['entry']), auth()->user());
                    Notification::make()->title('Registro finalizado')->success()->send();
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title(collect($exception->errors())->flatten()->first() ?: 'No se pudo finalizar')
                        ->danger()
                        ->send();
                }
            });
    }

    public function addendumAction(): Action
    {
        return Action::make('addendum')
            ->label('Adenda')
            ->form([
                Forms\Components\Textarea::make('summary')->label('Corrección')->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                app(CreateHistoryAddendum::class)->handle(
                    $this->entry((int) $arguments['entry']),
                    auth()->user(),
                    $data['summary'],
                );
                Notification::make()->title('Adenda agregada')->success()->send();
            });
    }

    public function shareAction(): Action
    {
        return Action::make('share')
            ->label('Compartir')
            ->form([
                Forms\Components\Select::make('case_id')
                    ->label(terminology('case', 'Caso'))
                    ->options(
                        CaseRecord::query()
                            ->where('subject_id', $this->subject->id)
                            ->orderByDesc('id')
                            ->get()
                            ->mapWithKeys(fn (CaseRecord $case) => [$case->id => $case->code ?: $case->title])
                    )
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                try {
                    $share = app(ShareHistoryEntry::class)->handle(
                        $this->entry((int) $arguments['entry']),
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
                    ->title($share->wasRecentlyCreated ? 'Compartido' : 'Ya estaba compartido')
                    ->success()
                    ->send();
            });
    }

    public function salesOrderAction(): Action
    {
        return Action::make('salesOrder')
            ->label('Orden de venta')
            ->action(function (array $arguments) {
                try {
                    $order = app(PrepareSalesOrderFromHistory::class)->handle(
                        $this->entry((int) $arguments['entry']),
                        auth()->user(),
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title(collect($exception->errors())->flatten()->first() ?: 'No se pudo preparar la orden')
                        ->danger()
                        ->send();

                    return;
                }

                $page = $order->isDraft() ? 'edit' : 'view';

                return redirect()->to(SalesOrderResource::getUrl($page, ['record' => $order]));
            });
    }

    public function proposedSummary(SubjectHistoryEntry $entry): ?string
    {
        $entry->loadMissing('type');

        return HistoryFieldSchema::proposedSummary($entry->type?->field_schema, $entry->payload);
    }

    public function canManage(): bool
    {
        return HistoryAccess::canManageSubject(auth()->user(), $this->subject);
    }

    public function canSell(SubjectHistoryEntry $entry): bool
    {
        return $entry->isFinal()
            && $entry->addendum_of_id === null
            && SalesAccess::canManageOrders(auth()->user(), $this->subject->organization)
            && HistoryAccess::canViewEntry(auth()->user(), $entry);
    }

    public function render()
    {
        return view('livewire.subject-history-timeline');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getForms(): array
    {
        return [];
    }

    protected function entry(int $id): SubjectHistoryEntry
    {
        $entry = HistoryAccess::entriesQueryForSubject($this->subject)->whereKey($id)->firstOrFail();
        abort_unless(HistoryAccess::canViewEntry(auth()->user(), $entry), 403);

        return $entry;
    }
}
