<?php

namespace App\Support;

use App\Models\AcademicTerm;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\SubjectTeacher;
use App\Models\User;

/**
 * Builds a teacher's gradebook for one class (subject × section) in a term.
 * Shared by the gradebook page, the CSV exports and the printable report.
 */
class Gradebook
{
    public static function build(User $teacher, ?int $termId, ?int $classId): array
    {
        $terms  = AcademicTerm::orderByDesc('academic_year')->orderBy('term_number')->get();
        $termId = $termId ?: AcademicTerm::getCurrent()?->id;

        $classes = $teacher->teachingAssignments()
            ->with(['subject', 'section.gradeLevel', 'section.trackStrand'])
            ->get()
            ->filter(fn (SubjectTeacher $c) => ! $termId || (int) $c->subject->academic_term_id === $termId)
            ->sortBy(fn (SubjectTeacher $c) => $c->subject->code.' '.$c->section->name)
            ->values();

        $selected = ($classId ? $classes->first(fn ($c) => (int) $c->id === $classId) : null) ?? $classes->first();

        $students = collect();
        $quizzes  = collect();
        $grid     = collect();
        $stats    = null;

        if ($selected) {
            $students = User::students()->where('section_id', $selected->section_id)->orderBy('name')->get();

            $quizzes = Quiz::where('teacher_id', $teacher->id)
                ->where('subject_id', $selected->subject_id)
                ->forSection($selected->section_id)
                ->withCount('questions')
                ->orderBy('created_at')
                ->get();

            // [student_id => [quiz_id => QuizSubmission]]
            $grid = QuizSubmission::whereIn('quiz_id', $quizzes->pluck('id'))
                ->whereIn('student_id', $students->pluck('id'))
                ->get()
                ->groupBy('student_id')
                ->map(fn ($rows) => $rows->keyBy('quiz_id'));

            $all      = $grid->flatten(1);
            $possible = $students->count() * $quizzes->count();

            $stats = [
                'students'   => $students->count(),
                'quizzes'    => $quizzes->count(),
                'average'    => $all->isNotEmpty() ? (int) round($all->avg(fn ($s) => $s->percent())) : null,
                'pass_rate'  => $all->isNotEmpty() ? (int) round($all->where('passed', true)->count() / $all->count() * 100) : null,
                'completion' => $possible > 0 ? (int) round($all->count() / $possible * 100) : null,
            ];
        }

        return [
            'terms'    => $terms,
            'termId'   => $termId,
            'term'     => $terms->first(fn ($t) => (int) $t->id === $termId),
            'classes'  => $classes,
            'selected' => $selected,
            'students' => $students,
            'quizzes'  => $quizzes,
            'grid'     => $grid,
            'stats'    => $stats,
        ];
    }

    /** A student's average % across the quizzes they took (null if none). */
    public static function studentAverage($row): ?int
    {
        return $row && $row->isNotEmpty() ? (int) round($row->avg(fn ($s) => $s->percent())) : null;
    }
}
