<?php

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Datetime = 'datetime';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Checkbox = 'checkbox';
    case File = 'file';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto',
            self::Textarea => 'Texto largo',
            self::Number => 'Número',
            self::Date => 'Fecha',
            self::Datetime => 'Fecha y hora',
            self::Select => 'Selección',
            self::Multiselect => 'Selección múltiple',
            self::Checkbox => 'Casilla',
            self::File => 'Archivo',
        };
    }
}
