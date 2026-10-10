<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A student joining one class (subject × section) outside their own section.
 * Requested by a teacher or the student; only counts once a developer approves it.
 */
class ClassEnrollment extends Model
{
    protected $fillable = ['student_id', 'subject_teacher_id', 'status', 'requested_by', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return [
            'status'     => EnrollmentStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo   { return $this->belongsTo(User::class, 'student_id'); }
    public function offering(): BelongsTo  { return $this->belongsTo(SubjectTeacher::class, 'subject_teacher_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function decider(): BelongsTo   { return $this->belongsTo(User::class, 'decided_by'); }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Approved);
    }

    /**
     * Ask for $student to join $class. A rejected request can be asked again.
     * Returns null when a request is already waiting or approved.
     */
    public static function request(User $student, SubjectTeacher $class, User $by): ?self
    {
        $enrollment = self::firstOrNew(['student_id' => $student->id, 'subject_teacher_id' => $class->id]);

        if ($enrollment->exists && $enrollment->status !== EnrollmentStatus::Rejected) {
            return null;
        }

        try {
            $enrollment->fill([
                'status' => EnrollmentStatus::Pending, 'requested_by' => $by->id, 'decided_by' => null, 'decided_at' => null,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            return null; // the same request was just sent twice
        }

        return $enrollment;
    }

    public function decide(EnrollmentStatus $status, User $by): void
    {
        $this->update(['status' => $status, 'decided_by' => $by->id, 'decided_at' => now()]);
    }
}
