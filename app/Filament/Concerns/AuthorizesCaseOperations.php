<?php

namespace App\Filament\Concerns;

use App\Models\Agenda;
use App\Models\CaseRecord;
use App\Support\Cases\CaseOperationalAccess;

trait AuthorizesCaseOperations
{
    public static function canViewAny(): bool
    {
        return CaseOperationalAccess::canOperate();
    }

    public static function canCreate(): bool
    {
        return CaseOperationalAccess::canManage();
    }

    public static function canEdit($record): bool
    {
        if ($record instanceof Agenda) {
            return CaseOperationalAccess::canManageAgenda($record);
        }

        if ($record instanceof CaseRecord) {
            return CaseOperationalAccess::canManageCase($record);
        }

        return CaseOperationalAccess::canManage();
    }

    public static function canDelete($record): bool
    {
        return static::canEdit($record);
    }
}
