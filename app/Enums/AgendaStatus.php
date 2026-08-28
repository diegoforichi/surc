<?php

namespace App\Enums;

enum AgendaStatus: string
{
    case Planned = 'planned';
    case Confirmed = 'confirmed';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planificada',
            self::Confirmed => 'Confirmada',
            self::Done => 'Realizada',
            self::Cancelled => 'Cancelada',
        };
    }
}
