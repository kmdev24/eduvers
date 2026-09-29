<?php

namespace App\Http\Controllers;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\Section;
use App\Models\SubjectTeacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Developers broadcast school-wide (or to all teachers / all students / a section);
 * teachers post to the sections they teach.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $announcements = Announcement::with(['author', 'section'])
            ->when($user->isTeacher(), fn ($q) => $q->where('author_id', $user->id))
            ->feed()
            ->paginate(10);

        return view('announcements.index', [
            'announcements' => $announcements,
            'announcement'  => new Announcement(['audience' => $user->isDeveloper() ? AnnouncementAudience::Everyone : AnnouncementAudience::Section]),
            'sections'      => $this->sectionsFor($user),
            'audiences'     => $this->audiencesFor($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        Announcement::create($this->validated($request, $user) + ['author_id' => $user->id]);

        return redirect()->route('announcements.index')->with('status', 'Announcement posted.');
    }

    public function edit(Request $request, Announcement $announcement): View
    {
        Gate::authorize('update', $announcement);

        return view('announcements.edit', [
            'announcement' => $announcement,
            'sections'     => $this->sectionsFor($request->user()),
            'audiences'    => $this->audiencesFor($request->user()),
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        Gate::authorize('update', $announcement);

        $announcement->update($this->validated($request, $request->user()));

        return redirect()->route('announcements.index')->with('status', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('delete', $announcement);

        $announcement->delete();

        return redirect()->route('announcements.index')->with('status', 'Announcement deleted.');
    }

    /* ------------------------------------------------------------------ */

    private function validated(Request $request, User $user): array
    {
        $audiences  = collect($this->audiencesFor($user))->map->value->all();
        $sectionIds = $this->sectionsFor($user)->pluck('id')->all();

        $data = $request->validate([
            'title'      => ['required', 'string', 'max:150'],
            'body'       => ['required', 'string', 'max:5000'],
            'audience'   => ['required', Rule::in($audiences)],
            'section_id' => [
                Rule::requiredIf(fn () => $request->input('audience') === AnnouncementAudience::Section->value),
                'nullable', 'integer', Rule::in($sectionIds),
            ],
            'is_pinned'  => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ], [
            'section_id.required' => 'Choose which section should see this announcement.',
            'section_id.in'       => 'Choose one of your sections.',
            'expires_at.after'    => 'The expiry date must be in the future.',
        ]);

        $isSection = $data['audience'] === AnnouncementAudience::Section->value;

        return [
            'title'      => $data['title'],
            'body'       => $data['body'],
            'audience'   => $data['audience'],
            'section_id' => $isSection ? $data['section_id'] : null,
            'is_pinned'  => $request->boolean('is_pinned'),
            'expires_at' => $data['expires_at'] ?? null,
        ];
    }

    /** @return array<int, AnnouncementAudience> */
    private function audiencesFor(User $user): array
    {
        return $user->isDeveloper() ? AnnouncementAudience::cases() : [AnnouncementAudience::Section];
    }

    private function sectionsFor(User $user)
    {
        return Section::query()
            ->when($user->isTeacher(), fn ($q) => $q->whereIn(
                'id', SubjectTeacher::where('teacher_id', $user->id)->select('section_id')
            ))
            ->with('gradeLevel')
            ->orderBy('grade_level_id')
            ->orderBy('name')
            ->get();
    }
}
