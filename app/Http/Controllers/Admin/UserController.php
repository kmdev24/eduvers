<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeacherType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Section;
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
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->payload());

        return redirect()->route('admin.users.index')
            ->with('status', "{$user->name} was added as {$user->role->label()}.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user'     => $user,
            'sections' => $this->sectionOptions(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->payload())->save();

        // A user who is no longer a teacher should not keep a teaching load
        if (! $user->isTeacher()) {
            $user->teachingAssignments()->delete();
        }

        // A student moved into a section no longer needs a request to join that section's classes
        $user->classEnrollments()->whereHas('offering', fn ($q) => $q->where('section_id', $user->section_id))->delete();

        return redirect()->route('admin.users.index')
            ->with('status', "{$user->name}'s account was updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', "You can't delete your own account.");
        }

        if ($user->isAdmin() && User::admins()->count() <= 1) {
            return back()->with('error', 'EduVers needs at least one admin account.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "{$name} was deleted.");
    }

    /** Sections for the student dropdown, with enrolment counts. */
    private function sectionOptions()
    {
        return Section::with(['gradeLevel', 'trackStrand'])
            ->withCount('students')
            ->orderBy('grade_level_id')
            ->orderBy('name')
            ->get();
    }
}
