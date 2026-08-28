<?php

namespace App\Support\History;

use App\Models\Subject;
use App\Models\SubjectHistoryEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class HistoryDocuments
{
    /**
     * @return Collection<int, SubjectHistoryEntry>
     */
    public static function finalTimeline(Subject $subject): Collection
    {
        return HistoryAccess::entriesQueryForSubject($subject)
            ->whereNull('addendum_of_id')
            ->where('status', SubjectHistoryEntry::STATUS_FINAL)
            ->with(self::entryRelations())
            ->orderBy('occurred_at')
            ->get();
    }

    /**
     * @return array<int, string|array<string, mixed>>
     */
    public static function entryRelations(): array
    {
        return [
            'type',
            'author',
            'finalizer',
            'shares.case',
            'media',
            'addenda' => fn ($query) => $query
                ->orderBy('occurred_at')
                ->with(['author', 'finalizer', 'media', 'type']),
        ];
    }

    public static function latestWeight(Subject $subject): ?string
    {
        $entries = HistoryAccess::entriesQueryForSubject($subject)
            ->where('status', SubjectHistoryEntry::STATUS_FINAL)
            ->whereNull('addendum_of_id')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get(['payload', 'occurred_at']);

        foreach ($entries as $entry) {
            $weight = data_get($entry->payload, 'weight');

            if (is_numeric($weight) && (float) $weight > 0) {
                return rtrim(rtrim(number_format((float) $weight, 2, ',', ''), '0'), ',').' kg';
            }
        }

        return null;
    }

    /**
     * @return list<array{date: Carbon, label: string, summary: ?string}>
     */
    public static function upcomingEvents(Subject $subject, int $limit = 5): array
    {
        return HistoryAccess::entriesQueryForSubject($subject)
            ->where('status', SubjectHistoryEntry::STATUS_FINAL)
            ->whereNull('addendum_of_id')
            ->whereNotNull('next_due_at')
            ->with('type')
            ->orderBy('next_due_at')
            ->limit($limit)
            ->get()
            ->map(fn (SubjectHistoryEntry $entry): array => [
                'date' => $entry->next_due_at,
                'label' => $entry->type?->label ?? terminology('history_entry', 'Registro'),
                'summary' => $entry->summary,
            ])
            ->all();
    }
}
