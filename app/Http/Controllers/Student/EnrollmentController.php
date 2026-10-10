<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassEnrollment;
use App\Models\SubjectTeacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A student applies to join a class of their course outside their section (a developer approves). */
class EnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user()->load('section.trackStrand');

        return view('student.enrollments.index', [
            'student'  => $student,
            'classes'  => $this->openClasses($student)
                ->with(['subject.gradeLevel', 'section', 'teacher'])
                ->get()
                ->sortBy(fn (SubjectTeacher $c) => $c->subject->name.' '.$c->section->name)
                ->values(),
            'requests' => $student->classEnrollments()
                ->with(['offering.subject', 'offering.section', 'offering.teacher'])
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data    = $request->validate(['class_id' => ['required', 'integer']]);
        $student = $request->user();
        $class   = $this->openClasses($student)->with('subject')->findOrFail($data['class_id']);

        return ClassEnrollment::request($student, $class, $student)
            ? back()->with('status', "Application sent for {$class->subject->name}. You can open it once a developer approves it.")
            : back()->with('error', 'You already applied for this class.');
    }

    /**
     * Classes the student may apply for: subjects of their course (strand) or core subjects,
     * taught in another section, for a subject they don't take yet.
     */
    private function openClasses(User $student): Builder
    {
        if (! $student->section) {
            return SubjectTeacher::query()->whereRaw('1 = 0');
        }

        return SubjectTeacher::query()
            ->where('section_id', '!=', $student->section_id)
            ->whereNotIn('subject_id', $student->studentClasses()->select('subject_id'))
            ->whereHas('subject', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('track_strand_id', $student->section->track_strand_id)
                ->orWhereNull('track_strand_id')));
    }
}
