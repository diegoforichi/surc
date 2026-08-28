<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;

class PublicImageUpload
{
    public static function make(string $name, string $directory, string $label = 'Foto'): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->directory($directory)
            ->disk('public')
            ->visibility('public')
            ->fetchFileInformation(false)
            ->maxSize(2048);
    }
}
