<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'grade_level_id',
        'track_strand_id',
        'academic_term_id',
    ];

    /* Structure -------------------------------------------------------- */

    public function gradeLevel(): BelongsTo   { return $this->belongsTo(GradeLevel::class); }
    public function trackStrand(): BelongsTo  { return $this->belongsTo(TrackStrand::class); }
    public function academicTerm(): BelongsTo { return $this->belongsTo(AcademicTerm::class); }

    /* Teaching load ---------------------------------------------------- */

    public function assignments(): HasMany
    {
        return $this->hasMany(SubjectTeacher::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_teacher', 'subject_id', 'teacher_id')
                    ->using(SubjectTeacher::class)
                    ->withPivot(['id', 'section_id'])
                    ->withTimestamps();
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'subject_teacher')
                    ->using(SubjectTeacher::class)
                    ->withPivot(['id', 'teacher_id'])
                    ->withTimestamps();
    }

    /* Content ---------------------------------------------------------- */

    public function lessons(): HasMany { return $this->hasMany(Lesson::class); }
    public function quizzes(): HasMany { return $this->hasMany(Quiz::class); }

    /**
     * Sections that can take this subject: same grade level, and same strand
     * unless it is a core subject (no strand).
     */
    public function eligibleSections(): \Illuminate\Database\Eloquent\Builder
    {
        return Section::query()
            ->where('grade_level_id', $this->grade_level_id)
            ->when($this->track_strand_id, fn ($q) => $q->where('track_strand_id', $this->track_strand_id));
    }

    public function isCore(): bool
    {
        return $this->track_strand_id === null;
    }

    /** Assign (or reassign) a teacher to this subject for a section. */
    public function assignTeacher(User $teacher, Section $section): SubjectTeacher
    {
        return SubjectTeacher::updateOrCreate(
            ['subject_id' => $this->id, 'section_id' => $section->id],
            ['teacher_id' => $teacher->id],
        );
    }
}
