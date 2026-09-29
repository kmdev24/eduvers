<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\LessonRequest;
use App\Models\Lesson;
use App\Models\Subject;
use App\Support\StudentNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function index(Request $request): View
    {
        $teacher   = $request->user();
        $subjectId = $request->integer('subject_id') ?: null;

        $lessons = Lesson::with('subject')
            ->where('teacher_id', $teacher->id)
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('teacher.lessons.index', [
            'lessons'   => $lessons,
            'subjects'  => $teacher->taughtSubjects()->orderBy('code')->get(),
            'subjectId' => $subjectId,
        ]);
    }

    public function create(Request $request): View
    {
        return view('teacher.lessons.form', [
            'lesson'   => new Lesson(['subject_id' => $request->integer('subject_id') ?: null]),
            'subjects' => $request->user()->taughtSubjects()->with('gradeLevel')->orderBy('code')->get(),
        ]);
    }

    public function store(LessonRequest $request, StudentNotifier $notifier): RedirectResponse
    {
        $subject = Subject::findOrFail($request->integer('subject_id'));

        $lesson = new Lesson($request->safe()->only(['title', 'content', 'subject_id']));
        $lesson->academic_term_id = $subject->academic_term_id;
        $lesson->teacher_id = $request->user()->id;

        $this->handleAttachment($request, $lesson);
        $this->handleVideo($request, $lesson);
        $lesson->save();

        // In-app notification now, email alert in the background
        $notified = $notifier->lessonPosted($lesson);

        return redirect()->route('teacher.lessons.show', $lesson)
            ->with('status', 'Lesson published.'.$notifier->summary($notified));
    }

    public function show(Lesson $lesson): View
    {
        Gate::authorize('view', $lesson);

        return view('teacher.lessons.show', ['lesson' => $lesson->load('subject.gradeLevel')]);
    }

    public function edit(Request $request, Lesson $lesson): View
    {
        Gate::authorize('update', $lesson);

        return view('teacher.lessons.form', [
            'lesson'   => $lesson,
            'subjects' => $request->user()->taughtSubjects()->with('gradeLevel')->orderBy('code')->get(),
        ]);
    }

    public function update(LessonRequest $request, Lesson $lesson): RedirectResponse
    {
        Gate::authorize('update', $lesson);

        $lesson->fill($request->safe()->only(['title', 'content', 'subject_id']));
        if ($lesson->isDirty('subject_id')) {
            $lesson->academic_term_id = Subject::findOrFail($lesson->subject_id)->academic_term_id;
        }

        $this->handleAttachment($request, $lesson);
        $this->handleVideo($request, $lesson);
        $lesson->save();

        return redirect()->route('teacher.lessons.show', $lesson)->with('status', 'Lesson updated.');
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        Gate::authorize('delete', $lesson);

        $title = $lesson->title;
        $lesson->delete(); // attachment and video files are removed by the model

        return redirect()->route('teacher.lessons.index')->with('status', "\"{$title}\" was deleted.");
    }

    /**
     * Apply the chosen video source:
     *  - none:   remove any video
     *  - upload: store a new MP4/WebM/MOV on the private disk (keeps the current file if none chosen)
     *  - link:   save a YouTube / Vimeo / Google Drive link
     */
    private function handleVideo(LessonRequest $request, Lesson $lesson): void
    {
        $current = $lesson->hasVideoFile() ? 'upload' : (filled($lesson->video_url) ? 'link' : 'none');
        $source  = $request->input('video_source', $current);

        switch ($source) {
            case 'upload':
                $lesson->video_url = null;
                if ($request->hasFile('video')) {
                    $lesson->deleteVideoFile();
                    $lesson->video_path = $request->file('video')
                        ->store("lessons/{$lesson->subject_id}/videos", Lesson::DISK);
                }
                break;

            case 'link':
                $lesson->deleteVideoFile();
                $lesson->video_url = trim((string) $request->validated('video_url'));
                break;

            default: // none
                $lesson->deleteVideoFile();
                $lesson->video_url = null;
        }
    }

    /** Store, replace or remove the lesson's file on the private disk. */
    private function handleAttachment(LessonRequest $request, Lesson $lesson): void
    {
        if ($request->boolean('remove_attachment') || $request->hasFile('attachment')) {
            $lesson->deleteAttachment();
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');

            $lesson->file_path         = $file->store("lessons/{$lesson->subject_id}", Lesson::DISK);
            $lesson->original_filename = $file->getClientOriginalName();
            $lesson->file_size         = $file->getSize();
        }
    }
}
