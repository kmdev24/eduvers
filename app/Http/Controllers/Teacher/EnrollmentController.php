<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassEnrollment;
use App\Models\SubjectTeacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A teacher asks to add a student from another section to one of their classes (an admin approves). */
class EnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        $classes = $request->user()->teachingAssignments()
            ->with(['subject', 'section'])
            ->get()
            ->sortBy(fn (SubjectTeacher $c) => $c->subject->code.' '.$c->section->name)
            ->values();

        $selected = $classes->firstWhere('id', $request->integer('class')) ?? $classes->first();
        $search   = trim((string) $request->query('q'));

        // Students of the class's year level, not in it yet, without a waiting or approved request for it
        $students = $selected && $search !== ''
            ? User::students()
                ->with('section')
                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                ->where(fn ($q) => $q->whereNull('section_id')->orWhereHas('section', fn ($q) => $q
                    ->where('grade_level_id', $selected->subject->grade_level_id)
                    ->where('id', '!=', $selected->section_id)))
                ->whereDoesntHave('classEnrollments', fn ($q) => $q
                    ->where('subject_teacher_id', $selected->id)
                    ->where('status', '!=', EnrollmentStatus::Rejected))
                ->orderBy('name')
                ->limit(20)
                ->get()
            : collect();

        $requests = ClassEnrollment::with(['student.section', 'offering.subject', 'offering.section'])
            ->whereIn('subject_teacher_id', $classes->pluck('id'))
            ->latest()
            ->limit(50)
            ->get();

        return view('teacher.enrollments.index', compact('classes', 'selected', 'search', 'students', 'requests'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_id'   => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
        ]);

        $class   = $request->user()->teachingAssignments()->with('subject')->findOrFail($data['class_id']);
        $student = User::students()->with('section')->findOrFail($data['student_id']);

        if ((int) $student->section_id === (int) $class->section_id) {
            return back()->with('error', "{$student->name} is already in this class through their section.");
        }

        if ($student->section && (int) $student->section->grade_level_id !== (int) $class->subject->grade_level_id) {
            return back()->with('error', "{$student->name} is not in the same year level as this class.");
        }

        return ClassEnrollment::request($student, $class, $request->user())
            ? back()->with('status', "Request sent to add {$student->name} to {$class->subject->code}. An admin must approve it.")
            : back()->with('error', "{$student->name} already has a request for this class, or is already in it.");
    }
}
