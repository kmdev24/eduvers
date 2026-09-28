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

    public function availableSlots(): int
    {
        return max(0, $this->capacity - $this->students()->count());
    }

    public function isFull(): bool
    {
        return $this->availableSlots() === 0;
    }
}
