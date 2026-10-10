<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\ClassEnrollment;
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

        // Students an admin approved into one of these classes from another section
        $joined = ClassEnrollment::approved()->whereIn('subject_teacher_id', $assignments->pluck('id'))->get(['student_id', 'subject_teacher_id']);

        // [class id => number of students]: the section's, plus approved joiners
        $sectionCounts = User::students()
            ->whereIn('section_id', $sectionIds)
            ->selectRaw('section_id, COUNT(*) as total')
            ->groupBy('section_id')
            ->toBase()
            ->pluck('total', 'section_id');
        $studentCounts = $assignments->mapWithKeys(fn ($class) => [
            $class->id => (int) ($sectionCounts[$class->section_id] ?? 0) + $joined->where('subject_teacher_id', $class->id)->count(),
        ]);

        $myStudents = fn ($q) => $q->where(fn ($q) => $q->whereIn('section_id', $sectionIds)->orWhereIn('id', $joined->pluck('student_id')));

        $stats = [
            'classes'  => $assignments->count(),
            'students' => User::students()->where($myStudents)->count(), // each student once
            'lessons'  => Lesson::whereIn('subject_id', $subjectIds)->count(),
            'quizzes'  => Quiz::whereIn('subject_id', $subjectIds)->count(),
        ];

        // Latest submissions from this teacher's own students on their subjects
        $recentSubmissions = QuizSubmission::query()
            ->with(['student', 'quiz' => fn ($q) => $q->withCount('questions'), 'quiz.subject'])
            ->whereHas('quiz', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->whereHas('student', $myStudents)
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
