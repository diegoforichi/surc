<?php

namespace App\Enums;

enum ActorCategory: string
{
    case Professional = 'professional';
    case Client = 'client';
    case Specialist = 'specialist';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Professional => 'Profesional',
            self::Client => 'Cliente',
            self::Specialist => 'Especialista',
            self::Other => 'Otro',
        };
    }
}
