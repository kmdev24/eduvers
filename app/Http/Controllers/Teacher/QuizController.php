<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function index(Request $request): View
    {
        $teacher   = $request->user();
        $subjectId = $request->integer('subject_id') ?: null;

        $quizzes = Quiz::with(['subject', 'sections'])
            ->withCount(['questions', 'submissions'])
            ->where('teacher_id', $teacher->id)
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('teacher.quizzes.index', [
            'quizzes'   => $quizzes,
            'subjects'  => $teacher->taughtSubjects()->orderBy('code')->get(),
            'subjectId' => $subjectId,
        ]);
    }

    public function create(Request $request): View
    {
        return view('teacher.quizzes.create', [
            'subjects'  => $request->user()->taughtSubjects()->with('gradeLevel')->orderBy('code')->get(),
            'subjectId' => $request->integer('subject_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $request->user();

        $data = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:2000'],
            'subject_id'    => ['required', 'integer', Rule::in($teacher->taughtSubjects()->pluck('id')->all())],
            'passing_score' => ['required', 'integer', 'min:1', 'max:100'],
        ], ['subject_id.in' => 'Choose one of the subjects assigned to you.']);

        $subject = Subject::findOrFail($data['subject_id']);

        $quiz = Quiz::create($data + [
            'academic_term_id' => $subject->academic_term_id,
            'teacher_id'       => $teacher->id,
            'is_published'     => false,
        ]);

        // Target every section this teacher handles for the subject (can be changed later)
        $quiz->sections()->sync($teacher->sectionsForSubject($subject->id)->pluck('id'));

        return redirect()->route('teacher.quizzes.edit', $quiz)
            ->with('status', 'Quiz created. Now add your questions.');
    }

    /** Quiz builder: settings, target sections and questions. */
    public function edit(Request $request, Quiz $quiz): View
    {
        Gate::authorize('update', $quiz);

        $quiz->load(['subject.gradeLevel', 'questions', 'sections'])->loadCount('submissions');

        return view('teacher.quizzes.edit', [
            'quiz'              => $quiz,
            'availableSections' => $request->user()->sectionsForSubject($quiz->subject_id),
            'locked'            => $quiz->submissions_count > 0,
        ]);
    }

    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        Gate::authorize('update', $quiz);

        $available = $request->user()->sectionsForSubject($quiz->subject_id)->pluck('id')->all();

        $data = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string', 'max:2000'],
            'passing_score'  => ['required', 'integer', 'min:1', 'max:100'],
            'reveal_answers' => ['nullable', 'boolean'],
            'sections'       => [$quiz->is_published ? 'required' : 'nullable', 'array'],
            'sections.*'     => ['integer', Rule::in($available)],
        ], ['sections.required' => 'A published quiz needs at least one section.']);

        $quiz->update([
            'title'          => $data['title'],
            'description'    => $data['description'] ?? null,
            'passing_score'  => $data['passing_score'],
            'reveal_answers' => $request->boolean('reveal_answers'),
        ]);
        $quiz->sections()->sync($data['sections'] ?? []);

        return back()->with('status', 'Quiz settings saved.');
    }

    /** Publish or unpublish. */
    public function publish(Quiz $quiz): RedirectResponse
    {
        Gate::authorize('update', $quiz);

        if (! $quiz->is_published) {
            if (! $quiz->questions()->exists()) {
                return back()->with('error', 'Add at least one question before publishing.');
            }
            if (! $quiz->sections()->exists()) {
                return back()->with('error', 'Choose at least one section before publishing.');
            }

            $quiz->update(['is_published' => true, 'published_at' => now()]);

            return back()->with('status', 'Quiz published. Students in the selected sections can now take it.');
        }

        $quiz->update(['is_published' => false]);

        return back()->with('status', 'Quiz unpublished. Students can no longer start it.');
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        Gate::authorize('delete', $quiz);

        if ($quiz->submissions()->exists()) {
            return back()->with('error', 'Students have already taken this quiz. Unpublish it instead of deleting, so their grades are kept.');
        }

        $title = $quiz->title;
        $quiz->delete(); // questions and section links are removed by cascade

        return redirect()->route('teacher.quizzes.index')->with('status', "\"{$title}\" was deleted.");
    }
}
