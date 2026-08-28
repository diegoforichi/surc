<?php

namespace App\Actions\History;

use App\Models\SubjectHistoryEntry;
use App\Models\User;
use App\Support\History\HistoryAccess;
use App\Support\History\HistoryAudit;
use App\Support\History\HistoryFieldSchema;
use Illuminate\Validation\ValidationException;

class FinalizeHistoryEntry
{
    public function handle(SubjectHistoryEntry $entry, User $user): SubjectHistoryEntry
    {
        if (! HistoryAccess::canManageEntry($user, $entry) || ! $user->can('history.finalize')) {
            abort(403);
        }

        if ($entry->isFinal()) {
            return $entry;
        }

        $entry->loadMissing('type');
        $missing = HistoryFieldSchema::missingRequired($entry->type?->field_schema, $entry->payload);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'payload' => 'Complete los campos obligatorios antes de finalizar: '.implode(', ', $missing).'.',
            ]);
        }

        if (! filled($entry->summary)) {
            $proposed = HistoryFieldSchema::proposedSummary($entry->type?->field_schema, $entry->payload);

            if (filled($proposed)) {
                $entry->summary = $proposed;
            }
        }

        if (! filled($entry->summary)) {
            throw ValidationException::withMessages([
                'summary' => 'Indique un resumen o complete hallazgos, producto o resultados antes de finalizar.',
            ]);
        }

        $entry->forceFill([
            'status' => SubjectHistoryEntry::STATUS_FINAL,
            'finalized_by' => $user->id,
            'finalized_at' => now(),
        ])->save();

        HistoryAudit::log('history_entry_finalized', $entry, [
            'subject_id' => $entry->subject_id,
            'organization_id' => $entry->organization_id,
            'entry_id' => $entry->id,
        ]);

        return $entry->fresh();
    }
}
