<?php

namespace App\Support;

use App\Enums\EnrollmentStatus;
use App\Models\ClassEnrollment;
use App\Models\SubjectTeacher;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Every student in the school against one class, so a teacher or an admin can see who
 * is already in it and who can still be added — instead of a list that silently hides
 * the ones it cannot offer.
 *
 * `blockedReason()` is the single rule: the page greys a student out with it, and the
 * controllers refuse the same student with it, so the screen and the check never disagree.
 */
class ClassRoster
{
    /**
     * @return array{LengthAwarePaginator<User>, Collection<int, ClassEnrollment>}
     *         the students, and this class's enrollments keyed by student id
     */
    public static function for(SubjectTeacher $class, string $search = '', int $perPage = 25): array
    {
        $students = User::students()
            ->with('section.gradeLevel')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        // One query for the whole page, so the status below costs nothing per row
        $enrollments = ClassEnrollment::where('subject_teacher_id', $class->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        return [$students, $enrollments];
    }

    /** Why this student can't be added to the class, or null when they can. */
    public static function blockedReason(User $student, SubjectTeacher $class, ?ClassEnrollment $enrollment = null): ?string
    {
        $enrollment ??= ClassEnrollment::where('student_id', $student->id)
            ->where('subject_teacher_id', $class->id)
            ->first();

        // The class belongs to a section, so that section's year level and course are the
        // ones a student has to match. Their own are used, which hold even before they
        // are placed in a section; a student with neither set yet matches anything.
        $section = $class->section;

        return match (true) {
            (int) $student->section_id === (int) $class->section_id => 'already in this class',
            $enrollment?->status === EnrollmentStatus::Pending      => 'waiting for approval',
            $enrollment?->status === EnrollmentStatus::Approved     => 'already added',
            $student->grade_level_id && (int) $student->grade_level_id !== (int) $section->grade_level_id
                => 'not in '.$section->gradeLevel->name,
            $student->track_strand_id && (int) $student->track_strand_id !== (int) $section->track_strand_id
                => 'not in '.$section->trackStrand->name,
            default => null,
        };
    }
}
