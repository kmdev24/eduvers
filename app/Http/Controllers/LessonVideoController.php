<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams an uploaded lesson video from the private disk to users allowed to
 * view the lesson. BinaryFileResponse supports HTTP Range requests, so the
 * HTML5 player can seek without downloading the whole file first.
 */
class LessonVideoController extends Controller
{
    public function __invoke(Lesson $lesson): BinaryFileResponse
    {
        Gate::authorize('view', $lesson);

        $disk = Storage::disk(Lesson::DISK);
        abort_unless($lesson->hasVideoFile() && $disk->exists($lesson->video_path), 404, 'This video is no longer available.');

        return response()->file($disk->path($lesson->video_path), [
            'Content-Type'  => $lesson->videoMimeType(),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
