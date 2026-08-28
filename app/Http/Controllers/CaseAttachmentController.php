<?php

namespace App\Http\Controllers;

use App\Models\CaseRecord;
use App\Support\Cases\CaseOperationalAccess;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseAttachmentController extends Controller
{
    public function show(CaseRecord $case, Media $media): StreamedResponse
    {
        abort_unless(CaseOperationalAccess::canAccessCase($case), 403);
        abort_unless(
            $media->model_type === CaseRecord::class
            && (int) $media->model_id === (int) $case->id,
            404,
        );

        return $media->toResponse(request());
    }
}
