<?php

namespace App\Models;

use App\Enums\TeacherType;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int              $id
 * @property string           $name
 * @property string           $email
 * @property UserRole         $role
 * @property TeacherType|null $teacher_type
 * @property int|null         $section_id
 */
#[Fillable(['name', 'email', 'password', 'role', 'teacher_type', 'section_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => UserRole::class,
            'teacher_type'      => TeacherType::class,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Role helpers                                                       */
    /* ------------------------------------------------------------------ */

    public function isDeveloper(): bool { return $this->role === UserRole::Developer; }
    public function isTeacher(): bool   { return $this->role === UserRole::Teacher; }
    public function isStudent(): bool   { return $this->role === UserRole::Student; }

    /** Up to two initials for avatars, e.g. "Maria Santos" -> "MS". */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('');
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $value = $role instanceof UserRole ? $role : UserRole::from($role);
            if ($this->role === $value) {
                return true;
            }
        }

        return false;
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeDevelopers(Builder $query): Builder { return $query->where('role', UserRole::Developer); }
    public function scopeTeachers(Builder $query): Builder   { return $query->where('role', UserRole::Teacher); }
    public function scopeStudents(Builder $query): Builder   { return $query->where('role', UserRole::Student); }

    public function scopeOfTeacherType(Builder $query, TeacherType|string $type): Builder
    {
        return $query->where('teacher_type', $type);
    }

    /* ------------------------------------------------------------------ */
    /*  STUDENT relationships                                              */
    /* ------------------------------------------------------------------ */

    /** The section a student is enrolled in. */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function quizSubmissions(): HasMany
    {
        return $this->hasMany(QuizSubmission::class, 'student_id');
    }

    /** Whether the student's section takes this subject. */
    public function takesSubject(int $subjectId): bool
    {
        return $this->section_id !== null
            && SubjectTeacher::where('section_id', $this->section_id)->where('subject_id', $subjectId)->exists();
    }

    /** Subjects offered to the student's section (query builder). */
    public function enrolledSubjects(): Builder
    {
        return Subject::query()->whereHas(
            'assignments',
            fn (Builder $q) => $q->where('section_id', $this->section_id)
        );
    }

    /* ------------------------------------------------------------------ */
    /*  TEACHER relationships                                              */
    /* ------------------------------------------------------------------ */

    /** Announcements this user has posted. */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'author_id');
    }

    /** Raw teaching-load rows (subject + section per row). */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(SubjectTeacher::class, 'teacher_id');
    }

    /** Subjects this teacher is assigned to (query builder, no duplicates). */
    public function taughtSubjects(): Builder
    {
        return Subject::query()->whereIn(
            'id',
            SubjectTeacher::query()->where('teacher_id', $this->id)->select('subject_id')
        );
    }

    public function teachesSubject(int $subjectId): bool
    {
        return $this->teachingAssignments()->where('subject_id', $subjectId)->exists();
    }

    /** Sections this teacher handles for one subject. */
    public function sectionsForSubject(int $subjectId)
    {
        return Section::query()
            ->whereIn('id', SubjectTeacher::query()
                ->where('teacher_id', $this->id)
                ->where('subject_id', $subjectId)
                ->select('section_id'))
            ->orderBy('name')
            ->get();
    }

    /** Subjects this teacher handles; pivot carries section_id. */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher', 'teacher_id', 'subject_id')
                    ->using(SubjectTeacher::class)
                    ->withPivot(['id', 'section_id'])
                    ->withTimestamps();
    }

    /** Sections this teacher handles; pivot carries subject_id. */
    public function handledSections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'subject_teacher', 'teacher_id', 'section_id')
                    ->using(SubjectTeacher::class)
                    ->withPivot(['id', 'subject_id'])
                    ->withTimestamps();
    }
}
