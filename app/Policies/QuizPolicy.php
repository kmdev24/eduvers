<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

class QuizPolicy
{
    public function update(User $user, Quiz $quiz): bool
    {
        return $user->isTeacher() && (int) $quiz->teacher_id === (int) $user->id;
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->update($user, $quiz);
    }

    /** A student may take a published quiz that was published to their section. */
    public function take(User $user, Quiz $quiz): bool
    {
        return $user->isStudent()
            && $quiz->is_published
            && $user->section_id !== null
            && $quiz->sections()->where('sections.id', $user->section_id)->exists();
    }
}
