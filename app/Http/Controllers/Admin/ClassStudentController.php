<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassEnrollment;
use App\Models\SubjectTeacher;
use App\Models\User;
use App\Notifications\EnrollmentDecisionNotification;
use App\Support\ClassRoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * An admin puts a student straight into any class. No approval step: the admin is the
 * one who would approve it, so the row is created already approved.
 */
class ClassStudentController extends Controller
{
    public function index(Request $request): View
    {
        $classes = SubjectTeacher::with(['subject.gradeLevel', 'section'])
            ->get()
            ->sortBy(fn (SubjectTeacher $c) => $c->section->name.' '.$c->subject->code)
            ->values();

        $selected = $classes->firstWhere('id', $request->integer('class')) ?? $classes->first();
        $search   = trim((string) $request->query('q'));

        [$students, $enrollments] = $selected ? ClassRoster::for($selected, $search) : [null, collect()];

        return view('admin.class-students.index', compact('classes', 'selected', 'search', 'students', 'enrollments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_id'   => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
        ]);

        $class   = SubjectTeacher::with('subject.gradeLevel')->findOrFail($data['class_id']);
        $student = User::students()->with('section')->findOrFail($data['student_id']);

        if ($reason = ClassRoster::blockedReason($student, $class)) {
            return back()->with('error', "{$student->name} is {$reason}.");
        }

        $admin = $request->user();

        $enrollment = DB::transaction(function () use ($student, $class, $admin) {
            $enrollment = ClassEnrollment::request($student, $class, $admin);
            $enrollment?->decide(EnrollmentStatus::Approved, $admin);

            return $enrollment;
        });

        if (! $enrollment) {
            return back()->with('error', "{$student->name} already has a request for this class.");
        }

        try {
            $student->notify(EnrollmentDecisionNotification::for($enrollment));
        } catch (Throwable $e) {
            report($e); // mail/queue trouble must not undo the enrollment
        }

        return back()->with('status', "{$student->name} can now open {$class->subject->name}.");
    }
}
