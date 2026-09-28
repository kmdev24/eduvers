<?php

namespace App\Models;

use App\Enums\LevelType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeLevel extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'level_type'];

    protected function casts(): array
    {
        return ['level_type' => LevelType::class];
    }

    public function sections(): HasMany { return $this->hasMany(Section::class); }
    public function subjects(): HasMany { return $this->hasMany(Subject::class); }

    public function scopeShs(Builder $query): Builder     { return $query->where('level_type', LevelType::Shs); }
    public function scopeCollege(Builder $query): Builder { return $query->where('level_type', LevelType::College); }
}
