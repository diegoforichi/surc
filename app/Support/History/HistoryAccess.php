<?php

namespace App\Support\History;

use App\Models\CaseRecord;
use App\Models\Organization;
use App\Models\Subject;
use App\Models\SubjectHistoryEntry;
use App\Models\User;
use App\Support\Settings\NetworkSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HistoryAccess
{
    public static function networkEnabled(?User $user = null): bool
    {
        $user ??= auth()->user();
        $network = $user?->network;

        return (bool) NetworkSettings::get('modules.history_enabled', false, $network);
    }

    public static function organizationEnabled(Subject|Organization|null $target, ?User $user = null): bool
    {
        $organization = $target instanceof Subject
            ? $target->organization
            : $target;

        return self::networkEnabled($user)
            && $organization !== null
            && (bool) $organization->history_enabled;
    }

    public static function blocksHistoryContent(?User $user): bool
    {
        if ($user === null) {
            return true;
        }

        return $user->is_platform_owner || $user->isNetworkAdmin();
    }

    public static function canViewSubject(?User $user, ?Subject $subject): bool
    {
        if ($user === null || $subject === null || self::blocksHistoryContent($user)) {
            return false;
        }

        if (! $user->can('history.view') || ! self::organizationEnabled($subject, $user)) {
            return false;
        }

        if ((int) $user->network_id !== (int) $subject->network_id) {
            return false;
        }

        $fixedOrganizationId = $user->fixedOrganizationId();

        return $fixedOrganizationId !== null
            && (int) $fixedOrganizationId === (int) $subject->organization_id;
    }

    public static function canManageSubject(?User $user, ?Subject $subject): bool
    {
        return self::canViewSubject($user, $subject)
            && ($user?->can('history.manage') ?? false);
    }

    public static function canBrowseSubjects(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->can('cases.manage')) {
            return true;
        }

        if (self::blocksHistoryContent($user) || ! $user->can('history.view') || ! self::networkEnabled($user)) {
            return false;
        }

        $fixedOrganizationId = $user->fixedOrganizationId();

        if ($fixedOrganizationId === null) {
            return true;
        }

        return self::organizationEnabled($user->organization, $user);
    }

    public static function canViewEntry(?User $user, ?SubjectHistoryEntry $entry): bool
    {
        if ($entry === null || ! self::entryBelongsToItsSubject($entry)) {
            return false;
        }

        return self::canViewSubject($user, self::subjectOf($entry));
    }

    public static function canManageEntry(?User $user, ?SubjectHistoryEntry $entry): bool
    {
        return self::canViewEntry($user, $entry)
            && ($user?->can('history.manage') ?? false);
    }

    public static function canShareCase(?User $user, ?CaseRecord $case): bool
    {
        if ($user === null || $case === null || self::blocksHistoryContent($user)) {
            return false;
        }

        if (! $user->can('history.share') || $case->subject === null) {
            return false;
        }

        return self::canViewSubject($user, $case->subject)
            && (int) $user->network_id === (int) $case->network_id
            && (int) $user->fixedOrganizationId() === (int) $case->organization_id;
    }

    public static function canPrintSubject(?User $user, ?Subject $subject): bool
    {
        return self::canViewSubject($user, $subject)
            && ($user?->can('history.print') ?? false);
    }

    public static function canPrintEntry(?User $user, ?SubjectHistoryEntry $entry): bool
    {
        return self::canViewEntry($user, $entry)
            && $entry?->isFinal()
            && ($user?->can('history.print') ?? false);
    }

    public static function canViewReminders(?User $user): bool
    {
        if ($user === null || self::blocksHistoryContent($user) || ! $user->can('history.view')) {
            return false;
        }

        $organization = $user->organization;

        return $organization !== null
            && self::organizationEnabled($organization, $user);
    }

    public static function subjectOf(SubjectHistoryEntry $entry): ?Subject
    {
        if ($entry->relationLoaded('subject') && $entry->subject !== null) {
            return $entry->subject;
        }

        return Subject::withoutGlobalScopes()
            ->with([
                'organization' => fn ($query) => $query->withoutGlobalScopes()->with('network'),
            ])
            ->find($entry->subject_id);
    }

    /**
     * @return Collection<int, SubjectHistoryEntry>
     */
    public static function sharedEntriesForCase(CaseRecord $case): Collection
    {
        $subject = $case->subject;

        if ($subject === null) {
            return collect();
        }

        return SubjectHistoryEntry::query()
            ->where('subject_id', $subject->id)
            ->where('network_id', $subject->network_id)
            ->where('organization_id', $subject->organization_id)
            ->whereHas('shares', fn (Builder $query) => $query->where('case_id', $case->id))
            ->with(['type', 'author'])
            ->orderByDesc('occurred_at')
            ->get();
    }

    public static function entriesQueryForSubject(Subject $subject): Builder
    {
        return SubjectHistoryEntry::query()
            ->where('subject_id', $subject->id)
            ->where('network_id', $subject->network_id)
            ->where('organization_id', $subject->organization_id);
    }

    public static function remindersQueryForUser(User $user): Builder
    {
        $organizationId = $user->fixedOrganizationId();

        if ($organizationId === null) {
            return SubjectHistoryEntry::query()->whereRaw('1 = 0');
        }

        return SubjectHistoryEntry::query()
            ->where('network_id', $user->network_id)
            ->where('organization_id', $organizationId)
            ->where('status', SubjectHistoryEntry::STATUS_FINAL)
            ->whereNull('addendum_of_id')
            ->whereNotNull('next_due_at');
    }

    public static function entryBelongsToItsSubject(SubjectHistoryEntry $entry): bool
    {
        $subject = self::subjectOf($entry);

        if ($subject === null) {
            return false;
        }

        return (int) $entry->network_id === (int) $subject->network_id
            && (int) $entry->organization_id === (int) $subject->organization_id
            && (int) $entry->subject_id === (int) $subject->id;
    }
}
