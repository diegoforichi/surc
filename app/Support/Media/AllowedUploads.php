<?php

namespace App\Support\Media;

class AllowedUploads
{
    /**
     * @return array<int, string>
     */
    public static function mimes(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
    }

    /**
     * Kilobytes. 10 MB.
     */
    public static function maxKilobytes(): int
    {
        return 10240;
    }

    public static function validationRule(): string
    {
        return 'file|max:'.self::maxKilobytes().'|mimes:'.implode(',', self::mimes());
    }
}
