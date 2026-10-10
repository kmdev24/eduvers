<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\SubjectTeacher;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $student = $request->user()->load(['section.gradeLevel', 'section.trackStrand']);

        // The student's classes (section + approved extra classes), with the assigned teacher
        $classes = $student->studentClasses()
            ->with(['subject', 'teacher'])
            ->get()
            ->sortBy(fn (SubjectTeacher $offering) => $offering->subject->name)
            ->values();

        $subjectIds = $classes->pluck('subject_id');

        $submissions = $student->quizSubmissions()
            ->with(['quiz' => fn ($q) => $q->withCount('questions'), 'quiz.subject'])
            ->latest()
            ->get();

        // Published quizzes for the student's classes that they haven't taken yet
        $pendingQuery = Quiz::published()
            ->visibleTo($student)
            ->whereNotIn('id', $submissions->pluck('quiz_id'));

        $percentages = $submissions->map(fn (QuizSubmission $s) => $s->percent());

        $stats = [
            'subjects' => $classes->count(),
            'lessons'  => Lesson::whereIn('subject_id', $subjectIds)->count(),
            'pending'  => (clone $pendingQuery)->count(),
            'average'  => $percentages->isNotEmpty() ? round($percentages->avg()) : null,
        ];

        return view('student.dashboard', [
            'student'        => $student,
            'stats'          => $stats,
            'classes'        => $classes,
            'pendingQuizzes' => $pendingQuery->with('subject')->latest('published_at')->take(5)->get(),
            'recentLessons'  => Lesson::with('subject')->whereIn('subject_id', $subjectIds)->latest()->take(5)->get(),
            'recentScores'   => $submissions->take(5),
            'announcements'  => Announcement::with(['author', 'section'])->visibleTo($student)->active()->feed()->take(5)->get(),
        ]);
    }
}
