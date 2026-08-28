<?php

namespace App\Support\History;

use App\Models\CaseRecord;
use App\Models\Network;
use App\Models\Organization;
use App\Models\Subject;
use App\Models\SubjectHistoryEntry;
use App\Models\User;
use App\Support\Settings\NetworkSettings;
use Illuminate\Database\Eloquent\Collection;

class HistoryAccess
{
    public static function networkEnabled(?Network $network): bool
    {
        if ($network === null) {
            return false;
        }

        return (bool) NetworkSettings::get('modules.history_enabled', false, $network);
    }

    public static function organizationEnabled(?Organization $organization): bool
    {
        if ($organization === null) {
            return false;
        }

        return self::networkEnabled($organization->network)
            && (bool) $organization->history_enabled;
    }

    public static function canViewSubject(?User $user, Subject $subject): bool
    {
        if (self::blocksHistoryContent($user)) {
            return false;
        }

        if (! $user->can('history.view')) {
            return false;
        }

        if (! self::organizationEnabled($subject->organization)) {
            return false;
        }

        if ($user->network_id !== $subject->network_id) {
            return false;
        }

        $fixedOrganizationId = $user->fixedOrganizationId();

        if ($fixedOrganizationId !== null && $subject->organization_id !== $fixedOrganizationId) {
            return false;
        }

        return true;
    }

    public static function canManageSubject(?User $user, Subject $subject): bool
    {
        return self::canViewSubject($user, $subject) && ($user?->can('history.manage') ?? false);
    }

    public static function canBrowseSubjects(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->can('cases.manage')) {
            return true;
        }

        if (self::blocksHistoryContent($user) || ! $user->can('history.view') || ! self::networkEnabled($user->network)) {
            return false;
        }

        $fixedOrganizationId = $user->fixedOrganizationId();

        if ($fixedOrganizationId === null) {
            return true;
        }

        return self::organizationEnabled($user->organization);
    }

    public static function canViewEntry(?User $user, SubjectHistoryEntry $entry): bool
    {
        $subject = self::subjectOf($entry);

        return $subject !== null && self::canViewSubject($user, $subject);
    }

    public static function canManageEntry(?User $user, SubjectHistoryEntry $entry): bool
    {
        return self::canViewEntry($user, $entry) && ($user?->can('history.manage') ?? false);
    }

    public static function canPrintSubject(?User $user, Subject $subject): bool
    {
        return self::canViewSubject($user, $subject) && ($user?->can('history.print') ?? false);
    }

    public static function canPrintEntry(?User $user, SubjectHistoryEntry $entry): bool
    {
        return self::canViewEntry($user, $entry)
            && ($user?->can('history.print') ?? false)
            && $entry->isFinal();
    }

    public static function subjectOf(SubjectHistoryEntry $entry): ?Subject
    {
        return Subject::withoutGlobalScopes()
            ->with([
                'organization' => fn ($query) => $query->withoutGlobalScopes()->with('network'),
            ])
            ->find($entry->subject_id);
    }

    public static function canShareCase(?User $user, CaseRecord $case): bool
    {
        if (self::blocksHistoryContent($user)) {
            return false;
        }

        if (! $user->can('history.share')) {
            return false;
        }

        if ($case->subject === null || ! self::organizationEnabled($case->subject->organization)) {
            return false;
        }

        if ($user->network_id !== $case->network_id) {
            return false;
        }

        $fixedOrganizationId = $user->fixedOrganizationId();

        if ($fixedOrganizationId !== null && $case->organization_id !== $fixedOrganizationId) {
            return false;
        }

        return true;
    }

    /**
     * @return Collection<int, SubjectHistoryEntry>
     */
    public static function sharedEntriesForCase(CaseRecord $case): Collection
    {
        return SubjectHistoryEntry::query()
            ->with(['type', 'author'])
            ->whereHas('shares', fn ($query) => $query->where('case_id', $case->id))
            ->orderByDesc('occurred_at')
            ->get();
    }

    protected static function blocksHistoryContent(?User $user): bool
    {
        return $user === null
            || $user->is_platform_owner
            || $user->isNetworkAdmin();
    }
}
