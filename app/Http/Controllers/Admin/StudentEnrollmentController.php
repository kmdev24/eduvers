<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LevelType;
use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\TrackStrand;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Enroll students in a Senior High School strand or a College course.
 *
 * A strand or course is carried by the section, so enrolling is choosing one — and
 * because every subject of a strand is already a class of its sections, the student
 * picks up the whole programme in one step.
 */
class StudentEnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        $levelType = LevelType::tryFrom((string) $request->query('level_type'));
        $strandId  = $request->integer('strand') ?: null;
        $search    = trim((string) $request->query('q'));
        $onlyNew   = $request->query('show') !== 'all';

        $sections = Section::with(['gradeLevel', 'trackStrand'])
            ->withCount('students')
            ->when($levelType, fn ($q) => $q->whereHas('gradeLevel', fn ($g) => $g->where('level_type', $levelType)))
            ->when($strandId, fn ($q) => $q->where('track_strand_id', $strandId))
            ->get()
            ->sortBy(fn (Section $s) => $s->gradeLevel->name.' '.$s->trackStrand->name.' '.$s->name)
            ->groupBy(fn (Section $s) => $s->gradeLevel->level_type->label());

        $students = User::students()
            ->with(['section.gradeLevel', 'section.trackStrand', 'gradeLevel', 'trackStrand'])
            ->when($onlyNew, fn ($q) => $q->whereNull('section_id'))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.student-enrollment.index', [
            'sections'       => $sections,
            'strands'        => TrackStrand::orderBy('name')->get(),
            'students'       => $students,
            'waitingCount'   => User::students()->whereNull('section_id')->count(),
            'filters'        => ['level_type' => $levelType?->value, 'strand' => $strandId, 'q' => $search, 'show' => $onlyNew ? 'new' : 'all'],
        ]);
    }

    /**
     * What stops this student joining this section: a year level or a course they were
     * already admitted into. Null when nothing does. A student with neither set yet
     * (newly created) can go anywhere, and the section then sets both.
     */
    private function mismatch(User $student, Section $section): ?string
    {
        $wrongLevel  = $student->grade_level_id && (int) $student->grade_level_id !== (int) $section->grade_level_id;
        $wrongCourse = $student->track_strand_id && (int) $student->track_strand_id !== (int) $section->track_strand_id;

        return match (true) {
            $wrongLevel && $wrongCourse => 'another year level and course',
            $wrongLevel                 => 'another year level',
            $wrongCourse                => 'another strand or course',
            default                     => null,
        };
    }

    /**
     * Why some students were left alone. Moving a student to another year level or course
     * is a promotion or a transfer, not a bulk action, so it is done one at a time on
     * their own account page.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $students
     */
    private function otherLevelMessage($students, Section $section): string
    {
        $names = $students->take(3)->pluck('name')->implode(', ')
            .($students->count() > 3 ? ' and '.($students->count() - 3).' more' : '');

        return $students->count().' not enrolled ('.$names.'): already in another year level or course, and '
            ."{$section->name} is {$section->trackStrand->name} · {$section->gradeLevel->name}. "
            ."Change a student's year level or course from Users → Edit.";
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'section_id'    => ['required', 'integer', Rule::exists('sections', 'id')],
            'student_ids'   => ['required', 'array'],
            'student_ids.*' => ['integer'],
        ], [
            'student_ids.required' => 'Choose at least one student to enroll.',
        ]);

        $section  = Section::with(['gradeLevel', 'trackStrand'])->findOrFail($data['section_id']);
        $students = User::students()->with('section')->whereIn('id', $data['student_ids'])->get();

        // Students already in this section are not moving anywhere, so they take no slot
        $moving = $students->reject(fn (User $s) => (int) $s->section_id === $section->id)->values();

        // A student already admitted into a year level and course must not be moved out of
        // either by accident, whether it came from a section or was set before they had one
        [$otherLevel, $moving] = $moving->partition(fn (User $s) => $this->mismatch($s, $section) !== null);

        if ($moving->isEmpty()) {
            return back()->with('error', $otherLevel->isNotEmpty()
                ? $this->otherLevelMessage($otherLevel, $section)
                : 'Those students are already in '.$section->name.'.');
        }

        if ($moving->count() > $section->availableSlots()) {
            return back()->with('error', "{$section->name} has {$section->availableSlots()} free slot(s), but you chose {$moving->count()} students.");
        }

        DB::transaction(fn () => $moving->each(fn (User $student) => $student->enrollInSection($section)));

        $count  = $moving->count();
        $status = $count.' '.Str::plural('student', $count)
            ." enrolled in {$section->trackStrand->name} · {$section->gradeLevel->name} ({$section->name}).";

        return back()->with('status', $otherLevel->isEmpty()
            ? $status
            : $status.' '.$this->otherLevelMessage($otherLevel, $section));
    }
}
