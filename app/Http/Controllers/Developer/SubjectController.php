<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Developer\SubjectRequest;
use App\Models\AcademicTerm;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TrackStrand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $levelId = $request->integer('grade_level_id') ?: null;
        $termId  = $request->integer('academic_term_id') ?: null;
        $strand  = (string) $request->query('strand'); // '' | 'core' | strand id
        $search  = trim((string) $request->query('q'));

        $subjects = Subject::query()
            ->with(['gradeLevel', 'trackStrand', 'academicTerm'])
            ->withCount('assignments')
            ->when($levelId, fn ($q) => $q->where('grade_level_id', $levelId))
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->when($strand === 'core', fn ($q) => $q->whereNull('track_strand_id'))
            ->when(ctype_digit($strand), fn ($q) => $q->where('track_strand_id', (int) $strand))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereLike('code', "%{$search}%")
                ->orWhereLike('name', "%{$search}%")))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        // How many sections each subject could be taught in
        $sectionGroups = Section::query()->toBase()
            ->selectRaw('grade_level_id, track_strand_id, COUNT(*) as total')
            ->groupBy('grade_level_id', 'track_strand_id')
            ->get();

        $eligibleCounts = $subjects->getCollection()->mapWithKeys(fn (Subject $s) => [
            $s->id => (int) $sectionGroups
                ->where('grade_level_id', $s->grade_level_id)
                ->when($s->track_strand_id, fn ($c) => $c->where('track_strand_id', $s->track_strand_id))
                ->sum('total'),
        ]);

        return view('developer.subjects.index', [
            'subjects'       => $subjects,
            'eligibleCounts' => $eligibleCounts,
            'gradeLevels'    => GradeLevel::orderBy('id')->get(),
            'terms'          => AcademicTerm::orderBy('academic_year')->orderBy('term_number')->get(),
            'strands'        => TrackStrand::orderBy('category')->orderBy('name')->get(),
            'filters'        => ['grade_level_id' => $levelId, 'academic_term_id' => $termId, 'strand' => $strand, 'q' => $search],
        ]);
    }

    public function create(): View
    {
        $subject = new Subject(['academic_term_id' => AcademicTerm::getCurrent()?->id]);

        return view('developer.subjects.form', $this->formData($subject));
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        $subject = Subject::create($request->validated());

        return redirect()->route('developer.subjects.teachers.edit', $subject)
            ->with('status', "{$subject->code} was created. Now assign its teachers.");
    }

    public function edit(Subject $subject): View
    {
        return view('developer.subjects.form', $this->formData($subject));
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        // Remove teacher assignments to sections that no longer take this subject
        if ($subject->wasChanged(['grade_level_id', 'track_strand_id'])) {
            $subject->assignments()
                ->whereNotIn('section_id', $subject->eligibleSections()->select('id'))
                ->delete();
        }

        return redirect()->route('developer.subjects.index')
            ->with('status', "{$subject->code} was updated.");
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        if ($subject->lessons()->exists() || $subject->quizzes()->exists()) {
            return back()->with('error', "{$subject->code} has lessons or quizzes and can't be deleted.");
        }

        $code = $subject->code;
        $subject->delete(); // teacher assignments are removed by cascade

        return redirect()->route('developer.subjects.index')->with('status', "{$code} was deleted.");
    }

    private function formData(Subject $subject): array
    {
        return [
            'subject'     => $subject,
            'gradeLevels' => GradeLevel::orderBy('id')->get(),
            'strands'     => TrackStrand::orderBy('category')->orderBy('name')->get(),
            'terms'       => AcademicTerm::orderBy('academic_year')->orderBy('term_number')->get(),
        ];
    }
}
