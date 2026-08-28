<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\SubjectHistoryEntry;
use App\Support\History\HistoryAccess;
use App\Support\History\HistoryDocuments;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class HistoryReportController extends Controller
{
    public function entry(SubjectHistoryEntry $entry): Response
    {
        abort_unless(HistoryAccess::canPrintEntry(Auth::user(), $entry), 403);

        $entry->load(HistoryDocuments::entryRelations());
        $subject = HistoryAccess::subjectOf($entry);

        abort_unless($subject !== null, 404);
        $subject->loadMissing(['organization', 'owner']);

        $this->recordDownload('history_entry_pdf_downloaded', $entry, [
            'subject_id' => $entry->subject_id,
            'organization_id' => $entry->organization_id,
            'entry_id' => $entry->id,
        ]);

        $pdf = Pdf::loadView('history.entry', $this->viewData(
            subject: $subject,
            entries: collect([$entry]),
            title: 'Registro de '.terminology('history', 'historial'),
        ))->setPaper('a4', 'portrait');

        return $pdf->stream("historial-registro-{$entry->id}.pdf");
    }

    public function subject(Subject $subject): Response
    {
        abort_unless(HistoryAccess::canPrintSubject(Auth::user(), $subject), 403);

        $subject->loadMissing(['organization', 'owner']);
        $entries = HistoryDocuments::finalTimeline($subject);

        $this->recordDownload('subject_history_pdf_downloaded', $subject, [
            'subject_id' => $subject->id,
            'organization_id' => $subject->organization_id,
            'entries_count' => $entries->count(),
        ]);

        $pdf = Pdf::loadView('history.subject', $this->viewData(
            subject: $subject,
            entries: $entries,
            title: 'Ficha de '.terminology('history', 'historial'),
        ) + [
            'lastWeight' => HistoryDocuments::latestWeight($subject),
            'upcoming' => HistoryDocuments::upcomingEvents($subject, 8),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("ficha-historial-{$subject->id}.pdf");
    }

    /**
     * @param  Collection<int, SubjectHistoryEntry>  $entries
     * @return array<string, mixed>
     */
    protected function viewData(Subject $subject, $entries, string $title): array
    {
        $user = Auth::user();

        return [
            'title' => $title,
            'subject' => $subject,
            'entries' => $entries,
            'issuedBy' => $user?->name ?? '—',
            'issuedAt' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function recordDownload(string $event, Subject|SubjectHistoryEntry $performedOn, array $properties): void
    {
        activity('history')
            ->event($event)
            ->causedBy(Auth::user())
            ->performedOn($performedOn)
            ->withProperties($properties)
            ->log($event);
    }
}
