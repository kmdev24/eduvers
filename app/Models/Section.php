<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(fn (Section $section) => $section->syncClasses());

        static::updated(function (Section $section) {
            if ($section->wasChanged(['grade_level_id', 'track_strand_id'])) {
                $section->syncClasses();

                // Its students move with it (User::enrollInSection keeps the same rule)
                $section->students()->update([
                    'grade_level_id'  => $section->grade_level_id,
                    'track_strand_id' => $section->track_strand_id,
                ]);
            }
        });
    }

    protected $fillable = [
        'name',
        'capacity',
        'grade_level_id',
        'track_strand_id',
    ];

    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    /* Relationships ---------------------------------------------------- */

    public function gradeLevel(): BelongsTo  { return $this->belongsTo(GradeLevel::class); }
    public function trackStrand(): BelongsTo { return $this->belongsTo(TrackStrand::class); }

    /** Students enrolled in this section. */
    public function students(): HasMany
    {
        return $this->hasMany(User::class)->where('role', UserRole::Student);
    }

    /** Teaching-load rows for this section. */
    public function assignments(): HasMany
    {
        return $this->hasMany(SubjectTeacher::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher')
                    ->using(SubjectTeacher::class)
                    ->withPivot(['id', 'teacher_id'])
                    ->withTimestamps();
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_teacher', 'section_id', 'teacher_id')
                    ->using(SubjectTeacher::class)
                    ->withPivot(['id', 'subject_id'])
                    ->withTimestamps();
    }

    /** Quizzes published to this section. */
    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'quiz_section');
    }

    /* Helpers ---------------------------------------------------------- */

    /**
     * The mirror of Subject::eligibleSections(): every subject of this section's year level,
     * of its strand or core, exists as one of its classes. Adds the missing ones only.
     */
    public function syncClasses(): void
    {
        $missing = Subject::query()
            ->where('grade_level_id', $this->grade_level_id)
            ->where(fn ($q) => $q->where('track_strand_id', $this->track_strand_id)->orWhereNull('track_strand_id'))
            ->whereNotIn('id', $this->assignments()->select('subject_id'))
            ->pluck('id');

        SubjectTeacher::insert($missing->map(fn (int $subjectId) => [
            'subject_id' => $subjectId,
            'section_id' => $this->id,
            'teacher_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }

    public function availableSlots(): int
    {
        return max(0, $this->capacity - $this->students()->count());
    }

    public function isFull(): bool
    {
        return $this->availableSlots() === 0;
    }
}
