<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'student_id',
        'score',
        'total_items',
        'passed',
        'answers_json',
    ];

    protected function casts(): array
    {
        return [
            'score'        => 'decimal:2',
            'total_items'  => 'integer',
            'passed'       => 'boolean',
            'answers_json' => 'array',
        ];
    }

    public function quiz(): BelongsTo    { return $this->belongsTo(Quiz::class); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }

    /** Score as a whole-number percentage. */
    public function percent(): int
    {
        $total = $this->total_items ?: $this->quiz?->questions()->count();

        return $total > 0 ? (int) round((float) $this->score / $total * 100) : 0;
    }

    /** Kept for backwards compatibility with earlier code. */
    public function percentage(): float
    {
        return (float) $this->percent();
    }

    public function answerFor(int $questionId): ?string
    {
        return $this->answers_json[$questionId] ?? $this->answers_json[(string) $questionId] ?? null;
    }
}
