<?php

namespace App\Actions\History;

use App\Models\SubjectHistoryEntry;
use App\Models\User;
use App\Support\History\HistoryAccess;
use Illuminate\Validation\ValidationException;

class FinalizeHistoryEntry
{
    public function handle(SubjectHistoryEntry $entry, User $user): SubjectHistoryEntry
    {
        if (! HistoryAccess::canManageEntry($user, $entry)) {
            abort(403);
        }

        if (! $user->can('history.finalize')) {
            abort(403, 'No tiene permiso para finalizar registros.');
        }

        if ($entry->isFinal()) {
            throw ValidationException::withMessages([
                'status' => 'El registro ya está finalizado.',
            ]);
        }

        $entry->update([
            'status' => SubjectHistoryEntry::STATUS_FINAL,
            'finalized_by' => $user->id,
            'finalized_at' => now(),
        ]);

        return $entry->fresh();
    }
}
