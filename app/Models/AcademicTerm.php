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
        'starts_on',
        'ends_on',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'term_number' => 'integer',
            'is_current'  => 'boolean',
            'starts_on'   => 'date',
            'ends_on'     => 'date',
        ];
    }

    /* Relationships ---------------------------------------------------- */

    public function subjects(): HasMany { return $this->hasMany(Subject::class); }
    public function lessons(): HasMany  { return $this->hasMany(Lesson::class); }
    public function quizzes(): HasMany  { return $this->hasMany(Quiz::class); }

    /* Scopes & helpers ------------------------------------------------- */

    /** Marked current by hand. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /** Running today according to the school calendar. */
    public function scopeRunningToday(Builder $query): Builder
    {
        return $query->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->orderBy('starts_on');
    }

    /**
     * The term the school is in. The calendar decides it, so a new semester starts on its
     * own date; the hand-set flag is the fallback for terms with no dates yet.
     *
     * Usage: AcademicTerm::getCurrent()
     */
    public static function getCurrent(): ?self
    {
        return static::query()->runningToday()->first()
            ?? static::query()->current()->first();
    }

    /** Whether the calendar, rather than the flag, is what makes this term current. */
    public function isRunningToday(): bool
    {
        return $this->starts_on && $this->ends_on
            && $this->starts_on->lte(today()) && $this->ends_on->gte(today());
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
