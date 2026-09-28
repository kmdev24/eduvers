<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Developer\SectionRequest;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\TrackStrand;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function index(Request $request): View
    {
        $levelId  = $request->integer('grade_level_id') ?: null;
        $strandId = $request->integer('track_strand_id') ?: null;

        $sections = Section::query()
            ->with(['gradeLevel', 'trackStrand'])
            ->withCount(['students', 'assignments'])
            ->when($levelId, fn ($q) => $q->where('grade_level_id', $levelId))
            ->when($strandId, fn ($q) => $q->where('track_strand_id', $strandId))
            ->orderBy('grade_level_id')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $capacity = (int) Section::sum('capacity');
        $enrolled = User::students()->whereNotNull('section_id')->count();

        return view('developer.sections.index', [
            'sections'    => $sections,
            'gradeLevels' => GradeLevel::orderBy('id')->get(),
            'strands'     => TrackStrand::withCount('sections')->orderBy('category')->orderBy('name')->get(),
            'filters'     => ['grade_level_id' => $levelId, 'track_strand_id' => $strandId],
            'summary'     => [
                'sections' => Section::count(),
                'capacity' => $capacity,
                'enrolled' => $enrolled,
                'fill'     => $capacity > 0 ? round($enrolled / $capacity * 100) : 0,
            ],
        ]);
    }

    public function create(): View
    {
        return view('developer.sections.form', $this->formData(new Section(['capacity' => 40])));
    }

    public function store(SectionRequest $request): RedirectResponse
    {
        $section = Section::create($request->validated());

        return redirect()->route('developer.sections.index')
            ->with('status', "Section {$section->name} was created.");
    }

    public function edit(Section $section): View
    {
        return view('developer.sections.form', $this->formData($section->loadCount('students')));
    }

    public function update(SectionRequest $request, Section $section): RedirectResponse
    {
        $section->update($request->validated());

        // Drop teacher assignments for subjects that no longer fit this section
        $section->assignments()
            ->whereHas('subject', fn ($q) => $q->where(fn ($q) => $q
                ->where('grade_level_id', '!=', $section->grade_level_id)
                ->orWhere(fn ($q) => $q
                    ->whereNotNull('track_strand_id')
                    ->where('track_strand_id', '!=', $section->track_strand_id))))
            ->delete();

        return redirect()->route('developer.sections.index')
            ->with('status', "Section {$section->name} was updated.");
    }

    public function destroy(Section $section): RedirectResponse
    {
        $enrolled = $section->students()->count();

        if ($enrolled > 0) {
            return back()->with('error', "{$section->name} still has {$enrolled} student(s). Move them to another section first.");
        }

        $name = $section->name;
        $section->delete(); // teacher assignments are removed by cascade

        return redirect()->route('developer.sections.index')->with('status', "Section {$name} was deleted.");
    }

    private function formData(Section $section): array
    {
        return [
            'section'     => $section,
            'gradeLevels' => GradeLevel::orderBy('id')->get(),
            'strands'     => TrackStrand::orderBy('category')->orderBy('name')->get(),
        ];
    }
}
