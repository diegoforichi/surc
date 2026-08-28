<?php

namespace App\Actions\History;

use App\Models\SubjectHistoryEntry;
use App\Models\User;
use App\Support\History\HistoryAccess;
use Illuminate\Validation\ValidationException;

class CreateHistoryAddendum
{
    public function handle(SubjectHistoryEntry $entry, User $user, string $summary, array $payload = []): SubjectHistoryEntry
    {
        if (! HistoryAccess::canManageEntry($user, $entry) || ! $user->can('history.finalize')) {
            abort(403);
        }

        if (! $entry->isFinal()) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden agregar adendas a registros finalizados.',
            ]);
        }

        return SubjectHistoryEntry::create([
            'network_id' => $entry->network_id,
            'organization_id' => $entry->organization_id,
            'subject_id' => $entry->subject_id,
            'history_entry_type_id' => $entry->history_entry_type_id,
            'occurred_at' => now(),
            'summary' => $summary,
            'payload' => $payload,
            'status' => SubjectHistoryEntry::STATUS_FINAL,
            'author_user_id' => $user->id,
            'addendum_of_id' => $entry->id,
            'finalized_by' => $user->id,
            'finalized_at' => now(),
        ]);
    }
}
