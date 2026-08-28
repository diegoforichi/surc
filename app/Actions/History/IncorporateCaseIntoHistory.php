<?php

namespace App\Actions\History;

use App\Enums\CaseStatus;
use App\Models\CaseHistoryShare;
use App\Models\CaseRecord;
use App\Models\HistoryEntryType;
use App\Models\SubjectHistoryEntry;
use App\Models\User;
use App\Support\History\HistoryAccess;
use App\Support\History\HistoryAudit;
use Illuminate\Validation\ValidationException;

class IncorporateCaseIntoHistory
{
    public function handle(CaseRecord $case, User $user): SubjectHistoryEntry
    {
        if ($case->subject === null) {
            throw ValidationException::withMessages([
                'subject' => 'El caso no tiene un sujeto asociado.',
            ]);
        }

        if ($case->status !== CaseStatus::Closed) {
            throw ValidationException::withMessages([
                'status' => 'Solo se puede incorporar el resultado de un caso cerrado.',
            ]);
        }

        if (! filled($case->summary) && ! filled($case->title)) {
            throw ValidationException::withMessages([
                'summary' => 'El caso no tiene un resultado para incorporar.',
            ]);
        }

        if (! HistoryAccess::canManageSubject($user, $case->subject)) {
            abort(403, 'No tiene permiso para incorporar este registro al historial.');
        }

        $existing = SubjectHistoryEntry::query()
            ->where('source_case_id', $case->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $type = HistoryEntryType::query()
            ->where('network_id', $case->network_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if ($type === null) {
            throw ValidationException::withMessages([
                'type' => 'La red no tiene tipos de registro de historial configurados.',
            ]);
        }

        $metadata = $case->metadata ?? [];

        $entry = SubjectHistoryEntry::create([
            'network_id' => $case->network_id,
            'organization_id' => $case->organization_id,
            'subject_id' => $case->subject_id,
            'history_entry_type_id' => $type->id,
            'occurred_at' => $case->closed_at ?? $case->scheduled_at ?? now(),
            'summary' => $case->summary ?: $case->title,
            'payload' => [
                'from_case' => true,
                'case_code' => $case->code,
                'diagnosis' => $metadata['diagnosis'] ?? null,
                'treatment' => $metadata['treatment'] ?? null,
            ],
            'status' => SubjectHistoryEntry::STATUS_FINAL,
            'author_user_id' => $user->id,
            'source_case_id' => $case->id,
            'finalized_by' => $user->id,
            'finalized_at' => now(),
        ]);

        CaseHistoryShare::query()->firstOrCreate(
            [
                'case_id' => $case->id,
                'subject_history_entry_id' => $entry->id,
            ],
            [
                'shared_by' => $user->id,
                'shared_at' => now(),
            ],
        );

        HistoryAudit::log('history_entry_created', $entry, [
            'subject_id' => $entry->subject_id,
            'organization_id' => $entry->organization_id,
            'entry_id' => $entry->id,
            'source' => 'case',
            'case_id' => $case->id,
        ]);

        return $entry;
    }
}
