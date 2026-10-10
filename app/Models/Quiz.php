<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'passing_score',
        'reveal_answers',
        'is_published',
        'published_at',
        'subject_id',
        'academic_term_id',
        'teacher_id',
    ];

    protected function casts(): array
    {
        return [
            'passing_score'  => 'integer',
            'reveal_answers' => 'boolean',
            'is_published'   => 'boolean',
            'published_at'   => 'datetime',
        ];
    }

    public function subject(): BelongsTo      { return $this->belongsTo(Subject::class); }
    public function academicTerm(): BelongsTo { return $this->belongsTo(AcademicTerm::class); }
    public function teacher(): BelongsTo      { return $this->belongsTo(User::class, 'teacher_id'); }
    public function questions(): HasMany      { return $this->hasMany(QuizQuestion::class)->orderBy('id'); }
    public function submissions(): HasMany    { return $this->hasMany(QuizSubmission::class); }

    /** Sections this quiz is published to. */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'quiz_section');
    }

    /* Scopes ----------------------------------------------------------- */

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeForSection(Builder $query, ?int $sectionId): Builder
    {
        return $query->whereHas('sections', fn (Builder $q) => $q->where('sections.id', $sectionId));
    }

    /** Quizzes a student can see: published to their section, or to a class they joined (approved). */
    public function scopeVisibleTo(Builder $query, User $student): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->forSection($student->section_id)
            ->orWhereExists(fn ($joined) => $joined->selectRaw('1')
                ->from('class_enrollments')
                ->join('subject_teacher', 'subject_teacher.id', '=', 'class_enrollments.subject_teacher_id')
                ->join('quiz_section', 'quiz_section.section_id', '=', 'subject_teacher.section_id')
                ->whereColumn('quiz_section.quiz_id', 'quizzes.id')
                ->whereColumn('subject_teacher.subject_id', 'quizzes.subject_id')
                ->where('class_enrollments.student_id', $student->id)
                ->where('class_enrollments.status', EnrollmentStatus::Approved->value)));
    }

    /* Helpers ---------------------------------------------------------- */

    /** Questions can't change once students have submitted (scores would drift). */
    public function isLocked(): bool
    {
        return $this->submissions()->exists();
    }

    /**
     * Grade and store a student's single attempt.
     *
     * @param  array<int|string, string>  $answers  [question_id => chosen option key]
     */
    public function submitFor(User $student, array $answers): QuizSubmission
    {
        $questions = $this->questions()->get();

        // Keep only answers to this quiz's questions
        $clean = $questions->mapWithKeys(fn (QuizQuestion $q) => [$q->id => $answers[$q->id] ?? null])->all();

        $score   = $questions->filter(fn (QuizQuestion $q) => $q->isCorrect($clean[$q->id]))->count();
        $total   = $questions->count();
        $percent = $total > 0 ? $score / $total * 100 : 0;

        return $this->submissions()->create([
            'student_id'   => $student->id,
            'score'        => $score,
            'total_items'  => $total,
            'passed'       => $percent >= $this->passing_score,
            'answers_json' => $clean,
        ]);
    }
}
