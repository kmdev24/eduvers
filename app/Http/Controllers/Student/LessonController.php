<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function show(Lesson $lesson): View
    {
        Gate::authorize('view', $lesson);

        $lesson->load(['subject', 'teacher']);

        // Neighbouring lessons in the same subject, oldest → newest
        $siblings = Lesson::where('subject_id', $lesson->subject_id)->orderBy('created_at')->orderBy('id')->get(['id', 'title']);
        $index    = $siblings->search(fn ($l) => (int) $l->id === (int) $lesson->id);

        return view('student.lessons.show', [
            'lesson'   => $lesson,
            'previous' => $index !== false && $index > 0 ? $siblings[$index - 1] : null,
            'next'     => $index !== false ? $siblings->get($index + 1) : null,
        ]);
    }
}
