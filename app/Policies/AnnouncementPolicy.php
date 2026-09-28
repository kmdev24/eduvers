<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    /** Developers manage every announcement; teachers only their own. */
    public function update(User $user, Announcement $announcement): bool
    {
        return $user->isDeveloper()
            || ($user->isTeacher() && (int) $announcement->author_id === (int) $user->id);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }
}
