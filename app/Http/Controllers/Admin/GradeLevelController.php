<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LevelType;
use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Year levels: Grade 11 and 12 for Senior High School, 1st to 4th Year for College.
 * A section belongs to one, so a level has to exist before students can be enrolled in it.
 */
class GradeLevelController extends Controller
{
    public function index(): View
    {
        $levels = GradeLevel::withCount(['sections', 'subjects'])->orderBy('id')->get();

        return view('admin.levels.index', [
            'programmes' => collect(LevelType::cases())->map(fn (LevelType $type) => [
                'type'   => $type,
                'levels' => $levels->filter(fn (GradeLevel $l) => $l->level_type === $type)->values(),
            ]),
            'total' => $levels->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('level', $this->rules());

        $level = GradeLevel::create($data);

        return back()->with('status', "{$level->name} was added to {$level->level_type->label()}.");
    }

    public function edit(GradeLevel $level): View
    {
        return view('admin.levels.edit', ['level' => $level->loadCount(['sections', 'subjects'])]);
    }

    public function update(Request $request, GradeLevel $level): RedirectResponse
    {
        $data = $request->validate($this->rules($level));

        // Moving between SHS and College would orphan its sections' strands
        if ($data['level_type'] !== $level->level_type->value && $level->sections()->exists()) {
            return back()->withInput()->withErrors([
                'level_type' => "{$level->name} is used by sections, so it can't move between Senior High School and College.",
            ]);
        }

        $level->update($data);

        return redirect()->route('admin.levels.index')->with('status', "{$level->name} was updated.");
    }

    public function destroy(GradeLevel $level): RedirectResponse
    {
        if ($level->sections()->exists() || $level->subjects()->exists()) {
            return back()->with('error', "{$level->name} still has sections or subjects and can't be deleted.");
        }

        $name = $level->name;
        $level->delete();

        return back()->with('status', "{$name} was deleted.");
    }

    private function rules(?GradeLevel $level = null): array
    {
        return [
            'name'       => ['required', 'string', 'max:50', Rule::unique('grade_levels', 'name')->ignore($level)],
            'level_type' => ['required', Rule::enum(LevelType::class)],
        ];
    }
}
