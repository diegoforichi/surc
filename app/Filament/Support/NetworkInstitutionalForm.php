<?php

namespace App\Filament\Support;

use Filament\Forms;

class NetworkInstitutionalForm
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Forms\Components\Section::make('Sitio institucional')
                ->schema([
                    PublicImageUpload::make('logo_path', 'network-logos', 'Logo')
                        ->helperText('Ícono del encabezado.'),
                    PublicImageUpload::make('cover_path', 'network-covers', 'Foto de portada')
                        ->helperText('Foto junto al texto de la red.'),
                    Forms\Components\TextInput::make('slogan')
                        ->label('Slogan')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->label('Descripción')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('phone')
                        ->label('Teléfono')
                        ->tel()
                        ->maxLength(50),
                    Forms\Components\TextInput::make('email')
                        ->label('Correo')
                        ->email()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('whatsapp')
                        ->label('WhatsApp')
                        ->tel()
                        ->maxLength(50),
                    Forms\Components\Textarea::make('address')
                        ->label('Dirección')
                        ->rows(2),
                ]),
        ];
    }
}
