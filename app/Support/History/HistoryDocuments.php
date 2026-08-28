<?php

namespace App\Support\History;

use App\Models\Subject;
use App\Models\SubjectHistoryEntry;
use Illuminate\Database\Eloquent\Collection;

class HistoryDocuments
{
    /**
     * @return Collection<int, SubjectHistoryEntry>
     */
    public static function finalTimeline(Subject $subject): Collection
    {
        return SubjectHistoryEntry::query()
            ->where('subject_id', $subject->id)
            ->where('organization_id', $subject->organization_id)
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
}
