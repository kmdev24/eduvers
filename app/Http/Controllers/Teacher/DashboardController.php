<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $teacher = $request->user();

        $assignments = $teacher->teachingAssignments()
            ->with(['subject.academicTerm', 'section.gradeLevel', 'section.trackStrand'])
            ->get();

        $sectionIds = $assignments->pluck('section_id')->unique()->values();
        $subjectIds = $assignments->pluck('subject_id')->unique()->values();

        // [section_id => number of students]
        $studentCounts = User::students()
            ->whereIn('section_id', $sectionIds)
            ->selectRaw('section_id, COUNT(*) as total')
            ->groupBy('section_id')
            ->toBase()
            ->pluck('total', 'section_id');

        $stats = [
            'classes'  => $assignments->count(),
            'students' => (int) $studentCounts->sum(),
            'lessons'  => Lesson::whereIn('subject_id', $subjectIds)->count(),
            'quizzes'  => Quiz::whereIn('subject_id', $subjectIds)->count(),
        ];

        // Latest submissions from this teacher's own students on their subjects
        $recentSubmissions = QuizSubmission::query()
            ->with(['student', 'quiz' => fn ($q) => $q->withCount('questions'), 'quiz.subject'])
            ->whereHas('quiz', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->whereHas('student', fn ($q) => $q->whereIn('section_id', $sectionIds))
            ->latest()
            ->take(6)
            ->get();

        return view('teacher.dashboard', [
            'stats'             => $stats,
            'assignments'       => $assignments,
            'studentCounts'     => $studentCounts,
            'recentSubmissions' => $recentSubmissions,
            'announcements'     => Announcement::with(['author', 'section'])->visibleTo($teacher)->active()->feed()->take(5)->get(),
        ]);
    }
}
