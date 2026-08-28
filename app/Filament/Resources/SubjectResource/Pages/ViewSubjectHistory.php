<?php

namespace App\Filament\Resources\SubjectResource\Pages;

use App\Filament\Resources\SubjectResource;
use App\Models\Subject;
use App\Support\History\HistoryAccess;
use App\Support\History\HistoryDocuments;
use Filament\Actions;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewSubjectHistory extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SubjectResource::class;

    protected static string $view = 'filament.resources.subject-resource.pages.view-subject-history';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(HistoryAccess::canViewSubject(auth()->user(), $this->record), 403);
        $this->record->loadMissing(['organization', 'owner']);
    }

    public function getTitle(): string
    {
        return terminology('history', 'Historial');
    }

    public function getHeading(): string
    {
        /** @var Subject $subject */
        $subject = $this->getRecord();

        return $subject->label_name;
    }

    /**
     * @return array<string, mixed>
     */
    public function headerStats(): array
    {
        /** @var Subject $subject */
        $subject = $this->getRecord();

        return [
            'owner' => $subject->owner?->display_name,
            'organization' => $subject->organization?->name,
            'weight' => HistoryDocuments::latestWeight($subject),
            'upcoming' => HistoryDocuments::upcomingEvents($subject, 3),
        ];
    }

    protected function getHeaderActions(): array
    {
        /** @var Subject $subject */
        $subject = $this->getRecord();

        return [
            Actions\Action::make('pdf')
                ->label('Descargar ficha PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (): string => route('history.subjects.pdf', $subject))
                ->openUrlInNewTab()
                ->visible(fn (): bool => HistoryAccess::canPrintSubject(auth()->user(), $subject)),
            Actions\Action::make('ficha')
                ->label('Volver a la ficha')
                ->url(fn (): string => SubjectResource::getUrl(
                    SubjectResource::canEdit($subject) ? 'edit' : 'view',
                    ['record' => $subject],
                )),
        ];
    }
}
