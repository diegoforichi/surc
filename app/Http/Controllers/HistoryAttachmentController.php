<?php

namespace App\Http\Controllers;

use App\Models\SubjectHistoryEntry;
use App\Support\History\HistoryAccess;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoryAttachmentController extends Controller
{
    public function show(SubjectHistoryEntry $entry, Media $media): StreamedResponse
    {
        abort_unless(HistoryAccess::canViewEntry(auth()->user(), $entry), 403);
        abort_unless(
            $media->model_type === SubjectHistoryEntry::class
            && (int) $media->model_id === (int) $entry->id
            && $media->collection_name === 'attachments',
            404,
        );

        return $media->toResponse(request());
    }
}
