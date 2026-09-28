<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question',
        'options_json',
        'correct_answer',
    ];

    /** Hide the key when serialising to students. */
    protected $hidden = ['correct_answer'];

    protected function casts(): array
    {
        return ['options_json' => 'array'];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function isCorrect(?string $answer): bool
    {
        return $answer !== null
            && strcasecmp(trim($answer), trim($this->correct_answer)) === 0;
    }
}
