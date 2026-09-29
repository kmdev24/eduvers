<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user();

        $quizzes = $student->section_id
            ? Quiz::published()->forSection($student->section_id)
                ->with('subject')->withCount('questions')
                ->latest('published_at')->get()
            : collect();

        $submissions = $student->quizSubmissions()->get()->keyBy('quiz_id');

        return view('student.quizzes.index', [
            'pending'     => $quizzes->reject(fn (Quiz $q) => $submissions->has($q->id))->values(),
            'completed'   => $quizzes->filter(fn (Quiz $q) => $submissions->has($q->id))->values(),
            'submissions' => $submissions,
        ]);
    }

    /** Take the quiz, or see the result if it was already submitted. */
    public function show(Request $request, Quiz $quiz): View
    {
        $submission = $request->user()->quizSubmissions()->where('quiz_id', $quiz->id)->first();

        if ($submission) {
            return view('student.quizzes.result', [
                'quiz'       => $quiz->load('subject', 'questions'),
                'submission' => $submission,
            ]);
        }

        Gate::authorize('take', $quiz);

        return view('student.quizzes.take', ['quiz' => $quiz->load('subject', 'questions')]);
    }

    public function submit(Request $request, Quiz $quiz): RedirectResponse
    {
        $student = $request->user();

        if ($student->quizSubmissions()->where('quiz_id', $quiz->id)->exists()) {
            return redirect()->route('student.quizzes.show', $quiz)->with('error', 'You have already taken this quiz.');
        }

        Gate::authorize('take', $quiz);

        $questions = $quiz->questions()->get();

        $rules = $messages = [];
        foreach ($questions->values() as $i => $question) {
            /** @var QuizQuestion $question */
            $rules["answers.{$question->id}"] = ['required', Rule::in(array_keys($question->options_json ?? []))];
            $messages["answers.{$question->id}.required"] = 'Please answer question '.($i + 1).'.';
            $messages["answers.{$question->id}.in"] = 'Please choose a valid option for question '.($i + 1).'.';
        }

        $validated = $request->validate($rules, $messages);

        try {
            $submission = $quiz->submitFor($student, $validated['answers'] ?? []);
        } catch (UniqueConstraintViolationException) {
            // Double submit (e.g. clicked twice): the first attempt counts
            return redirect()->route('student.quizzes.show', $quiz);
        }

        return redirect()->route('student.quizzes.show', $quiz)->with('status', $submission->passed
            ? "Well done! You scored {$submission->percent()}% and passed."
            : "You scored {$submission->percent()}%. The passing score is {$quiz->passing_score}%.");
    }
}
