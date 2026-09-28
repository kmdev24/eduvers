<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Teaching-load pivot: teacher × subject × section.
 */
class SubjectTeacher extends Pivot
{
    protected $table = 'subject_teacher';

    public $incrementing = true;

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'section_id',
    ];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function section(): BelongsTo { return $this->belongsTo(Section::class); }
}
