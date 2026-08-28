<?php

namespace App\Support\History;

use App\Models\SubjectHistoryEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class HistoryAttachments
{
    public const DISK = 'local';

    /**
     * @param  mixed  $files
     */
    public static function attachFromUploads(SubjectHistoryEntry $entry, mixed $files): void
    {
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        foreach ($files as $file) {
            if ($file instanceof TemporaryUploadedFile || $file instanceof UploadedFile) {
                $entry
                    ->addMedia($file->getRealPath())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('attachments');

                continue;
            }

            if (! is_string($file) || $file === '') {
                continue;
            }

            $absolute = Storage::disk(self::DISK)->path($file);

            if (! is_file($absolute)) {
                continue;
            }

            $entry
                ->addMedia($absolute)
                ->toMediaCollection('attachments');
        }
    }
}
