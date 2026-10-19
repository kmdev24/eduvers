<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeacherType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\TrackStrand;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $role   = UserRole::tryFrom((string) $request->query('role'));
        $type   = TeacherType::tryFrom((string) $request->query('teacher_type'));
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->with(['section.gradeLevel'])
            // Admin cannot see developer accounts at all — they're hidden
            ->when($request->user()->isAdmin(), fn ($q) => $q->where('role', '!=', UserRole::Developer->value))
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($type, fn ($q) => $q->where('teacher_type', $type))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // ['admin' => 1, 'teacher' => 4, 'student' => 120]
        $roleCounts = User::query()->toBase()
            // Don't show developer count to admins
            ->when($request->user()->isAdmin(), fn ($q) => $q->where('role', '!=', UserRole::Developer->value))
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('admin.users.index', [
            'users'      => $users,
            'roleCounts' => $roleCounts,
            'filters'    => ['role' => $role?->value, 'teacher_type' => $type?->value, 'q' => $search],
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user'     => new User(),
            'sections' => $this->sectionOptions(),
            'levels'   => $this->levelOptions(),
            'strands'  => TrackStrand::orderBy('name')->get(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->payload());

        return redirect()->route('admin.users.index')
            ->with('status', "{$user->name} was added as {$user->role->label()}.");
    }

    public function edit(Request $request, User $user): View
    {
        // Admin cannot edit a developer
        if ($request->user()->isAdmin() && $user->isDeveloper()) {
            abort(403, 'You cannot edit a developer account.');
        }

        return view('admin.users.form', [
            'user'     => $user,
            'sections' => $this->sectionOptions(),
            'levels'   => $this->levelOptions(),
            'strands'  => TrackStrand::orderBy('name')->get(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        // Admin cannot update a developer
        if ($request->user()->isAdmin() && $user->isDeveloper()) {
            abort(403, 'You cannot edit a developer account.');
        }

        $user->fill($request->payload())->save();

        // A user who is no longer a teacher should not keep a teaching load. The classes
        // stay (their students are enrolled in them); they just lose their teacher.
        if (! $user->isTeacher()) {
            $user->teachingAssignments()->update(['teacher_id' => null]);
        }

        // A student moved into a section no longer needs a request to join that section's classes
        $user->dropRedundantClassEnrollments();

        return redirect()->route('admin.users.index')
            ->with('status', "{$user->name}'s account was updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', "You can't delete your own account.");
        }

        // Admin cannot delete a developer
        if ($request->user()->isAdmin() && $user->isDeveloper()) {
            return back()->with('error', 'You cannot delete a developer account.');
        }

        if ($user->isAdmin() && User::admins()->count() <= 1) {
            return back()->with('error', 'EduVers needs at least one admin account.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "{$name} was deleted.");
    }

    /** Year levels for the student dropdown, Senior High first. */
    private function levelOptions()
    {
        return GradeLevel::orderBy('level_type')->orderBy('id')->get();
    }

    /** Sections for the student dropdown, with enrollment counts. */
    private function sectionOptions()
    {
        return Section::with(['gradeLevel', 'trackStrand'])
            ->withCount('students')
            ->orderBy('grade_level_id')
            ->orderBy('name')
            ->get();
    }
}
