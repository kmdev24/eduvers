<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class QuizQuestionController extends Controller
{
    /** Option keys a question can have (A and B are required). */
    public const KEYS = ['A', 'B', 'C', 'D'];

    public function store(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->guard($quiz);

        $quiz->questions()->create($this->validated($request, 'question'));

        return redirect()->to(route('teacher.quizzes.edit', $quiz).'#questions')->with('status', 'Question added.');
    }

    public function edit(Quiz $quiz, QuizQuestion $question): View
    {
        $this->guard($quiz);

        return view('teacher.quizzes.question', ['quiz' => $quiz, 'question' => $question]);
    }

    public function update(Request $request, Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        $this->guard($quiz);

        $question->update($this->validated($request));

        return redirect()->to(route('teacher.quizzes.edit', $quiz).'#questions')->with('status', 'Question updated.');
    }

    public function destroy(Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        $this->guard($quiz);

        $question->delete();

        if ($quiz->is_published && ! $quiz->questions()->exists()) {
            $quiz->update(['is_published' => false]);
        }

        return redirect()->to(route('teacher.quizzes.edit', $quiz).'#questions')->with('status', 'Question removed.');
    }

    /* ------------------------------------------------------------------ */

    private function guard(Quiz $quiz): void
    {
        Gate::authorize('update', $quiz);

        abort_if($quiz->isLocked(), 403, 'Students have already taken this quiz, so its questions can no longer be changed.');
    }

    private function validated(Request $request, string $bag = 'default'): array
    {
        $validator = validator($request->all(), [
            'question'       => ['required', 'string', 'max:2000'],
            'options'        => ['required', 'array'],
            'options.A'      => ['required', 'string', 'max:500'],
            'options.B'      => ['required', 'string', 'max:500'],
            'options.C'      => ['nullable', 'string', 'max:500'],
            'options.D'      => ['nullable', 'string', 'max:500'],
            'correct_answer' => ['required', 'in:'.implode(',', self::KEYS)],
        ], [
            'options.A.required'      => 'Option A is required.',
            'options.B.required'      => 'Option B is required.',
            'correct_answer.required' => 'Mark which option is correct.',
        ]);

        $validator->after(function (Validator $v) use ($request) {
            $key = $request->input('correct_answer');
            if ($key && blank($request->input("options.{$key}"))) {
                $v->errors()->add('correct_answer', "The correct answer ({$key}) can't be an empty option.");
            }
        });

        $data = $validator->validateWithBag($bag);

        return [
            'question'       => $data['question'],
            'options_json'   => collect(self::KEYS)
                ->mapWithKeys(fn ($k) => [$k => trim((string) ($data['options'][$k] ?? ''))])
                ->filter(fn ($text) => $text !== '')
                ->all(),
            'correct_answer' => $data['correct_answer'],
        ];
    }
}
