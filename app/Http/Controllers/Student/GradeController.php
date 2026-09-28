<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\QuizSubmission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $submissions = $request->user()->quizSubmissions()
            ->with(['quiz.subject', 'quiz.academicTerm'])
            ->latest()
            ->get();

        // Term → subject → submissions
        $byTerm = $submissions
            ->groupBy(fn (QuizSubmission $s) => $s->quiz->academicTerm
                ? $s->quiz->academicTerm->name.($s->quiz->academicTerm->academic_year ? ' · '.$s->quiz->academicTerm->academic_year : '')
                : 'Unassigned term')
            ->map(fn ($termRows) => $termRows->groupBy(fn (QuizSubmission $s) => $s->quiz->subject->name));

        return view('student.grades.index', [
            'byTerm'  => $byTerm,
            'summary' => [
                'taken'   => $submissions->count(),
                'passed'  => $submissions->where('passed', true)->count(),
                'average' => $submissions->isNotEmpty() ? (int) round($submissions->avg(fn ($s) => $s->percent())) : null,
            ],
        ]);
    }
}
