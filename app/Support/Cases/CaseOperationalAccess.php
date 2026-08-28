<?php

namespace App\Support\Cases;

use App\Models\Agenda;
use App\Models\CaseRecord;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CaseOperationalAccess
{
    public static function canOperate(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->can('cases.manage') || $user->can('cases.operate');
    }

    public static function canManage(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user?->can('cases.manage') ?? false;
    }

    public static function canManageAgenda(?Model $agenda, ?User $user = null): bool
    {
        if (! $agenda instanceof Agenda || ! static::canManage($user)) {
            return false;
        }

        $user ??= auth()->user();
        $fixedOrganizationId = $user?->fixedOrganizationId();

        return $fixedOrganizationId === null || $agenda->organization_id === $fixedOrganizationId;
    }

    public static function canManageCase(?CaseRecord $case, ?User $user = null): bool
    {
        if ($case === null || ! static::canManage($user)) {
            return false;
        }

        $user ??= auth()->user();
        $fixedOrganizationId = $user?->fixedOrganizationId();

        return $fixedOrganizationId === null || $case->organization_id === $fixedOrganizationId;
    }

    public static function isSpecialistOnly(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null || $user->can('cases.manage')) {
            return false;
        }

        return $user->can('cases.operate') && $user->hasRole('specialist');
    }

    /**
     * @return array<int, int>
     */
    public static function linkedPartyIds(?User $user = null): array
    {
        $user ??= auth()->user();

        if ($user === null) {
            return [];
        }

        return Party::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->all();
    }

    public static function canAccessCase(CaseRecord $case, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null || ! static::canOperate($user)) {
            return false;
        }

        if ($user->is_platform_owner) {
            return true;
        }

        if ($user->network_id !== $case->network_id) {
            return false;
        }

        $fixedOrganizationId = $user->fixedOrganizationId();

        if ($fixedOrganizationId !== null && ! static::caseVisibleToOrganization($case, $fixedOrganizationId)) {
            return false;
        }

        if (static::isSpecialistOnly($user)) {
            $partyIds = static::linkedPartyIds($user);

            if ($partyIds === []) {
                return false;
            }

            return $case->agenda()
                ->whereIn('specialist_party_id', $partyIds)
                ->exists();
        }

        return true;
    }

    public static function scopeAgendasForUser(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        $fixedOrganizationId = $user?->fixedOrganizationId();

        if ($fixedOrganizationId !== null) {
            $query->where(function (Builder $builder) use ($fixedOrganizationId): void {
                $builder
                    ->where('organization_id', $fixedOrganizationId)
                    ->orWhere('is_shared', true);
            });
        }

        if (static::isSpecialistOnly($user)) {
            $partyIds = static::linkedPartyIds($user);

            return $query->whereIn('specialist_party_id', $partyIds !== [] ? $partyIds : [-1]);
        }

        return $query;
    }

    public static function scopeCasesForUser(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        $fixedOrganizationId = $user?->fixedOrganizationId();

        if ($fixedOrganizationId !== null) {
            $query->where(function (Builder $builder) use ($fixedOrganizationId): void {
                $builder
                    ->where('organization_id', $fixedOrganizationId)
                    ->orWhereHas(
                        'agenda',
                        fn (Builder $agendaQuery) => $agendaQuery->where('organization_id', $fixedOrganizationId),
                    );
            });
        }

        if (static::isSpecialistOnly($user)) {
            $partyIds = static::linkedPartyIds($user);

            return $query->whereHas(
                'agenda',
                fn (Builder $agendaQuery) => $agendaQuery->whereIn(
                    'specialist_party_id',
                    $partyIds !== [] ? $partyIds : [-1],
                ),
            );
        }

        return $query;
    }

    protected static function caseVisibleToOrganization(CaseRecord $case, int $organizationId): bool
    {
        if ($case->organization_id === $organizationId) {
            return true;
        }

        return static::agendaOrganizationId($case) === $organizationId;
    }

    protected static function agendaOrganizationId(CaseRecord $case): ?int
    {
        if ($case->relationLoaded('agenda')) {
            return $case->agenda?->organization_id;
        }

        $organizationId = $case->agenda()->value('organization_id');

        return $organizationId !== null ? (int) $organizationId : null;
    }
}
