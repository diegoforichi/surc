<?php

namespace App\Actions\History;

use App\Models\HistoryEntryType;
use App\Models\Subject;
use App\Models\SubjectHistoryEntry;
use App\Models\User;
use App\Support\History\HistoryAccess;
use App\Support\History\HistoryAttachments;
use App\Support\History\HistoryAudit;
use App\Support\History\HistoryFieldSchema;

class PersistHistoryEntry
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Subject $subject, User $user, array $data, ?SubjectHistoryEntry $entry = null): SubjectHistoryEntry
    {
        if ($entry === null && ! HistoryAccess::canManageSubject($user, $subject)) {
            abort(403);
        }

        if ($entry !== null && ! HistoryAccess::canManageEntry($user, $entry)) {
            abort(403);
        }

        $files = $data['attachment_files'] ?? [];
        unset($data['attachment_files']);

        $typeId = (int) ($data['history_entry_type_id'] ?? $entry?->history_entry_type_id ?? 0);
        $type = $typeId > 0
            ? HistoryEntryType::query()
                ->where('network_id', $subject->network_id)
                ->find($typeId)
            : null;
        $data['payload'] = HistoryFieldSchema::extractPayload($type?->field_schema, $data['payload'] ?? []);

        if ($entry) {
            $entry->update($data);
        } else {
            $data['network_id'] = $subject->network_id;
            $data['organization_id'] = $subject->organization_id;
            $data['subject_id'] = $subject->id;
            $data['author_user_id'] = $user->id;
            $data['status'] = SubjectHistoryEntry::STATUS_DRAFT;
            $entry = SubjectHistoryEntry::create($data);

            HistoryAudit::log('history_entry_created', $entry, [
                'subject_id' => $subject->id,
                'organization_id' => $subject->organization_id,
                'entry_id' => $entry->id,
            ]);
        }

        HistoryAttachments::attachFromUploads($entry, $files);

        return $entry->fresh() ?? $entry;
    }
}
