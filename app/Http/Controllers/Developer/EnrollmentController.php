<?php

namespace App\Http\Controllers\Developer;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassEnrollment;
use App\Notifications\EnrollmentDecisionNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Approve or reject requests to join a class outside the student's section. */
class EnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        $status = EnrollmentStatus::tryFrom((string) $request->query('status')) ?? EnrollmentStatus::Pending;

        return view('developer.enrollments.index', [
            'status'      => $status,
            'counts'      => ClassEnrollment::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->toBase()->pluck('total', 'status'),
            'enrollments' => ClassEnrollment::with(['student.section', 'offering.subject', 'offering.section', 'offering.teacher', 'requester', 'decider'])
                ->where('status', $status)
                ->latest()
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function update(Request $request, ClassEnrollment $enrollment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([EnrollmentStatus::Approved->value, EnrollmentStatus::Rejected->value])],
        ]);

        $enrollment->decide(EnrollmentStatus::from($data['status']), $request->user());
        $enrollment->load(['student', 'offering.subject']);

        try {
            $enrollment->student->notify(EnrollmentDecisionNotification::for($enrollment));
        } catch (Throwable $e) {
            report($e); // mail/queue trouble must not undo the decision
        }

        return back()->with('status', $enrollment->status === EnrollmentStatus::Approved
            ? "{$enrollment->student->name} can now open {$enrollment->offering->subject->name}."
            : "{$enrollment->student->name} was not added to {$enrollment->offering->subject->name}.");
    }
}
