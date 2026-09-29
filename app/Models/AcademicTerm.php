<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AcademicTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'term_number',
        'academic_year',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'term_number' => 'integer',
            'is_current'  => 'boolean',
        ];
    }

    /* Relationships ---------------------------------------------------- */

    public function subjects(): HasMany { return $this->hasMany(Subject::class); }
    public function lessons(): HasMany  { return $this->hasMany(Lesson::class); }
    public function quizzes(): HasMany  { return $this->hasMany(Quiz::class); }

    /* Scopes & helpers ------------------------------------------------- */

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /** Usage: AcademicTerm::getCurrent() */
    public static function getCurrent(): ?self
    {
        return static::query()->current()->first();
    }

    /** Mark this term as the only current term. */
    public function makeCurrent(): void
    {
        DB::transaction(function () {
            static::query()->where('is_current', true)->update(['is_current' => false]);
            $this->forceFill(['is_current' => true])->save();
        });
    }
}
