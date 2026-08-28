<?php

namespace App\Support\Contact;

class WhatsappLink
{
    public static function url(?string $number): ?string
    {
        if (! filled($number)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($digits === null || $digits === '') {
            return null;
        }

        return 'https://wa.me/'.$digits;
    }
}
