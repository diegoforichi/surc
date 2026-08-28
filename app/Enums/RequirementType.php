<?php

namespace App\Enums;

enum RequirementType: string
{
    case Checkbox = 'checkbox';
    case Payment = 'payment';
    case File = 'file';
    case Field = 'field';
    case Approval = 'approval';

    public function label(): string
    {
        return match ($this) {
            self::Checkbox => 'Casilla',
            self::Payment => 'Pago / Seña',
            self::File => 'Archivo',
            self::Field => 'Campo',
            self::Approval => 'Aprobación',
        };
    }
}
