<?php

namespace App\Support\Sales;

use App\Models\Organization;
use App\Models\SalesCatalogItem;
use App\Models\SalesOrder;
use App\Models\Subject;
use App\Models\User;
use App\Support\History\HistoryAccess;
use Illuminate\Database\Eloquent\Builder;

class SalesAccess
{
    public static function blocksCommercialContent(?User $user): bool
    {
        if ($user === null) {
            return true;
        }

        return $user->is_platform_owner
            || $user->isNetworkAdmin()
            || $user->hasRole('specialist');
    }

    public static function canManageCatalog(?User $user, ?Organization $organization = null): bool
    {
        if ($user === null || self::blocksCommercialContent($user) || ! $user->can('sales.catalog.manage')) {
            return false;
        }

        $organizationId = $user->fixedOrganizationId();

        if ($organizationId === null) {
            return false;
        }

        if ($organization !== null && (int) $organization->id !== (int) $organizationId) {
            return false;
        }

        return (int) $user->network_id === (int) ($organization?->network_id ?? $user->network_id);
    }

    public static function canManageOrders(?User $user, ?Organization $organization = null): bool
    {
        if ($user === null || self::blocksCommercialContent($user) || ! $user->can('sales.orders.manage')) {
            return false;
        }

        $organizationId = $user->fixedOrganizationId();

        if ($organizationId === null) {
            return false;
        }

        if ($organization !== null && (int) $organization->id !== (int) $organizationId) {
            return false;
        }

        return true;
    }

    public static function canExportOrders(?User $user, ?Organization $organization = null): bool
    {
        return self::canManageOrders($user, $organization)
            && ($user?->can('sales.orders.export') ?? false);
    }

    public static function canViewOrder(?User $user, ?SalesOrder $order): bool
    {
        if ($order === null || ! self::canManageOrders($user, $order->organization)) {
            return false;
        }

        $subject = $order->relationLoaded('subject')
            ? $order->subject
            : Subject::query()->find($order->subject_id);

        return HistoryAccess::canViewSubject($user, $subject);
    }

    public static function canEditOrder(?User $user, ?SalesOrder $order): bool
    {
        return self::canViewOrder($user, $order) && $order?->isDraft();
    }

    public static function canViewCatalogItem(?User $user, ?SalesCatalogItem $item): bool
    {
        if ($item === null) {
            return false;
        }

        return self::canManageCatalog($user, $item->organization)
            || self::canManageOrders($user, $item->organization);
    }

    public static function scopeOrdersForUser(Builder $query, ?User $user): Builder
    {
        if ($user === null || self::blocksCommercialContent($user) || ! $user->can('sales.orders.manage')) {
            return $query->whereRaw('1 = 0');
        }

        $organizationId = $user->fixedOrganizationId();

        if ($organizationId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('network_id', $user->network_id)
            ->where('organization_id', $organizationId);
    }

    public static function scopeCatalogForUser(Builder $query, ?User $user, bool $activeOnly = false): Builder
    {
        if ($user === null || self::blocksCommercialContent($user)) {
            return $query->whereRaw('1 = 0');
        }

        $organizationId = $user->fixedOrganizationId();

        if ($organizationId === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('sales.catalog.manage') || $user->can('sales.orders.manage')) {
            $query
                ->where('network_id', $user->network_id)
                ->where('organization_id', $organizationId);

            if ($activeOnly && ! $user->can('sales.catalog.manage')) {
                $query->where('is_active', true);
            }

            return $query;
        }

        return $query->whereRaw('1 = 0');
    }
}
