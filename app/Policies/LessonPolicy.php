<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    /** Read the lesson and download its attachment. */
    public function view(User $user, Lesson $lesson): bool
    {
        return match (true) {
            $user->isDeveloper() => true,
            $user->isTeacher()   => (int) $lesson->teacher_id === (int) $user->id || $user->teachesSubject($lesson->subject_id),
            $user->isStudent()   => $user->takesSubject($lesson->subject_id),
            default              => false,
        };
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->isTeacher() && (int) $lesson->teacher_id === (int) $user->id;
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson);
    }
}
