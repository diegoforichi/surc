<?php

namespace App\Actions\History;

use App\Models\CaseHistoryShare;
use App\Models\CaseRecord;
use App\Models\SubjectHistoryEntry;
use App\Models\User;
use App\Support\History\HistoryAccess;
use App\Support\History\HistoryAudit;
use Illuminate\Validation\ValidationException;

class ShareHistoryEntry
{
    public function handle(SubjectHistoryEntry $entry, CaseRecord $case, User $user): CaseHistoryShare
    {
        if (! $entry->isFinal()) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden compartir registros finalizados.',
            ]);
        }

        if ((int) $case->subject_id !== (int) $entry->subject_id) {
            throw ValidationException::withMessages([
                'case_id' => 'El caso no corresponde a este sujeto.',
            ]);
        }

        if (! HistoryAccess::canShareCase($user, $case)) {
            abort(403, 'No tiene permiso para compartir este registro.');
        }

        $share = CaseHistoryShare::query()->firstOrCreate(
            [
                'case_id' => $case->id,
                'subject_history_entry_id' => $entry->id,
            ],
            [
                'shared_by' => $user->id,
                'shared_at' => now(),
            ],
        );

        if ($share->wasRecentlyCreated) {
            HistoryAudit::log('history_entry_shared', $entry, [
                'subject_id' => $entry->subject_id,
                'organization_id' => $entry->organization_id,
                'entry_id' => $entry->id,
                'case_id' => $case->id,
            ]);
        }

        return $share;
    }
}
