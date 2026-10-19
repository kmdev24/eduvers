<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassEnrollment;
use App\Models\SubjectTeacher;
use App\Models\User;
use App\Support\ClassRoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A teacher asks to add a student from another section to one of their classes (an admin approves). */
class EnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        $classes = $request->user()->teachingAssignments()
            ->with(['subject', 'section.gradeLevel', 'section.trackStrand'])
            ->get()
            ->sortBy(fn (SubjectTeacher $c) => $c->subject->code.' '.$c->section->name)
            ->values();

        $selected = $classes->firstWhere('id', $request->integer('class')) ?? $classes->first();
        $search   = trim((string) $request->query('q'));

        [$students, $enrollments] = $selected ? ClassRoster::for($selected, $search) : [null, collect()];

        $roster = [
            'selected'    => $selected,
            'students'    => $students,
            'enrollments' => $enrollments,
            'action'      => route('teacher.enrollments.store'),
            'addLabel'    => $selected ? 'Add to '.$selected->subject->code : 'Add',
        ];

        // The list on its own, for the live refresh as the teacher types or picks a class
        if ($request->boolean('roster')) {
            return view('partials.class-roster', $roster);
        }

        $requests = ClassEnrollment::with(['student.section', 'offering.subject', 'offering.section'])
            ->whereIn('subject_teacher_id', $classes->pluck('id'))
            ->latest()
            ->limit(50)
            ->get();

        return view('teacher.enrollments.index', $roster + compact('classes', 'search', 'requests'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_id'   => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
        ]);

        $class   = $request->user()->teachingAssignments()->with('subject.gradeLevel')->findOrFail($data['class_id']);
        $student = User::students()->with('section')->findOrFail($data['student_id']);

        if ($reason = ClassRoster::blockedReason($student, $class)) {
            return back()->with('error', "{$student->name} is {$reason}.");
        }

        return ClassEnrollment::request($student, $class, $request->user())
            ? back()->with('status', "Request sent to add {$student->name} to {$class->subject->code}. An admin must approve it.")
            : back()->with('error', "{$student->name} already has a request for this class.");
    }
}
