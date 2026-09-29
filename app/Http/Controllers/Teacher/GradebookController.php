<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Gradebook;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GradebookController extends Controller
{
    public function index(Request $request): View
    {
        return view('teacher.gradebook.index', $this->data($request));
    }

    /** ?type=grades (one row per student) | submissions (one row per attempt) */
    public function export(Request $request): StreamedResponse
    {
        $data = $this->data($request);
        abort_unless($data['selected'], 404, 'No class selected.');

        ['selected' => $class, 'students' => $students, 'quizzes' => $quizzes, 'grid' => $grid] = $data;

        $base = Str::slug($class->subject->code.' '.$class->section->name);
        $date = now()->format('Y-m-d');

        if ($request->query('type') === 'submissions') {
            $rows = [];
            foreach ($students as $student) {
                foreach ($quizzes as $quiz) {
                    $sub = $grid->get($student->id)?->get($quiz->id);
                    $rows[] = [
                        $student->name,
                        $student->email,
                        $quiz->title,
                        $sub ? (float) $sub->score : '',
                        $sub ? $sub->total_items : $quiz->questions_count,
                        $sub ? $sub->percent() : '',
                        $sub ? ($sub->passed ? 'Passed' : 'Failed') : 'Not taken',
                        $sub?->created_at?->format('Y-m-d H:i') ?? '',
                    ];
                }
            }

            return Csv::download("{$base}-submissions-{$date}.csv",
                ['Student', 'Email', 'Quiz', 'Score', 'Items', 'Percent', 'Result', 'Submitted at'], $rows);
        }

        $headers = array_merge(['No.', 'Student', 'Email'], $quizzes->map(fn ($q) => $q->title.' (%)')->all(), ['Average (%)', 'Passed', 'Taken']);

        $rows = $students->values()->map(function ($student, $i) use ($quizzes, $grid) {
            $row = $grid->get($student->id, collect());

            return array_merge(
                [$i + 1, $student->name, $student->email],
                $quizzes->map(fn ($q) => $row->get($q->id)?->percent() ?? '')->all(),
                [Gradebook::studentAverage($row) ?? '', $row->where('passed', true)->count(), $row->count().'/'.$quizzes->count()],
            );
        });

        return Csv::download("{$base}-grades-{$date}.csv", $headers, $rows);
    }

    /** Printable report (use the browser's "Save as PDF"). */
    public function print(Request $request): View
    {
        $data = $this->data($request);
        abort_unless($data['selected'], 404, 'No class selected.');

        return view('reports.gradebook', $data + ['teacher' => $request->user()]);
    }

    private function data(Request $request): array
    {
        return Gradebook::build(
            $request->user(),
            $request->integer('term') ?: null,
            $request->integer('class') ?: null,
        );
    }
}
