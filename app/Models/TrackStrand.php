<?php

namespace App\Models;

use App\Enums\TrackCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackStrand extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category'];

    protected function casts(): array
    {
        return ['category' => TrackCategory::class];
    }

    public function sections(): HasMany { return $this->hasMany(Section::class); }
    public function subjects(): HasMany { return $this->hasMany(Subject::class); }

    /**
     * College programs (e.g. HRS) pair with College levels only;
     * Academic / TechPro strands pair with SHS levels only.
     */
    public function isCompatibleWith(GradeLevel $level): bool
    {
        return $this->category->isCollege()
            === ($level->level_type === \App\Enums\LevelType::College);
    }

    public function scopeOfCategory(Builder $query, TrackCategory|string $category): Builder
    {
        return $query->where('category', $category);
    }
}
