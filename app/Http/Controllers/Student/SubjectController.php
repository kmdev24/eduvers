<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user()->load('section.gradeLevel', 'section.trackStrand');
        $term    = AcademicTerm::getCurrent();

        $classes = $student->studentClasses()
            ->inTerm($term?->id)
            ->with(['subject.academicTerm', 'teacher', 'section'])
            ->get()
            ->sortBy(fn (SubjectTeacher $c) => $c->subject->name)
            ->values();

        $subjectIds = $classes->pluck('subject_id');

        $lessonCounts = Lesson::whereIn('subject_id', $subjectIds)
            ->selectRaw('subject_id, COUNT(*) as total')->groupBy('subject_id')
            ->toBase()->pluck('total', 'subject_id');

        $quizCounts = Quiz::published()->visibleTo($student)->whereIn('subject_id', $subjectIds)
            ->selectRaw('subject_id, COUNT(*) as total')->groupBy('subject_id')
            ->toBase()->pluck('total', 'subject_id');

        return view('student.subjects.index', compact('student', 'term', 'classes', 'lessonCounts', 'quizCounts'));
    }

    public function show(Request $request, Subject $subject): View
    {
        $student = $request->user();
        abort_unless($student->takesSubject($subject->id), 403, 'You are not enrolled in this subject.');

        $teacher = $student->studentClasses()->with('teacher')->where('subject_id', $subject->id)->first()?->teacher;

        $quizzes = Quiz::published()->visibleTo($student)
            ->where('subject_id', $subject->id)
            ->withCount('questions')
            ->latest('published_at')
            ->get();

        return view('student.subjects.show', [
            'subject'     => $subject->load('gradeLevel', 'academicTerm', 'trackStrand'),
            'teacher'     => $teacher,
            'lessons'     => $subject->lessons()->with('teacher')->latest()->get(),
            'quizzes'     => $quizzes,
            'submissions' => $student->quizSubmissions()->whereIn('quiz_id', $quizzes->pluck('id'))->get()->keyBy('quiz_id'),
        ]);
    }
}
