<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves lesson files from the private disk to users allowed to view the lesson.
 * ?inline=1 opens PDFs in the browser instead of downloading.
 */
class LessonAttachmentController extends Controller
{
    public function __invoke(Request $request, Lesson $lesson): StreamedResponse
    {
        Gate::authorize('view', $lesson);

        $disk = Storage::disk(Lesson::DISK);
        abort_unless($lesson->hasAttachment() && $disk->exists($lesson->file_path), 404, 'This file is no longer available.');

        $name = $lesson->original_filename ?: basename($lesson->file_path);

        return $request->boolean('inline') && $lesson->isPdf()
            ? $disk->response($lesson->file_path, $name)
            : $disk->download($lesson->file_path, $name);
    }
}
