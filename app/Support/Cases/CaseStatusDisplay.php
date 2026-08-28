<?php

namespace App\Support\Cases;

use App\Enums\CaseStatus;

class CaseStatusDisplay
{
    public static function label(CaseStatus|string $status): string
    {
        if (is_string($status)) {
            $status = CaseStatus::from($status);
        }

        return match ($status) {
            CaseStatus::Open => 'En curso',
            CaseStatus::Closed => terminology('ux.status_attended', 'Resuelto'),
            CaseStatus::Cancelled => 'Cancelado',
        };
    }
}
