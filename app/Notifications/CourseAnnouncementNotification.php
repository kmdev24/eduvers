<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Support\Str;

class CourseAnnouncementNotification extends StudentActivityNotification
{
    public static function for(Announcement $announcement): self
    {
        $announcement->loadMissing(['author', 'section']);

        return new self([
            'kind'     => 'announcement',
            'icon'     => 'megaphone',
            'headline' => $announcement->is_pinned ? 'Pinned announcement' : 'New announcement',
            'title'    => $announcement->title,
            'excerpt'  => Str::limit(trim(preg_replace('/\s+/', ' ', $announcement->body)), 220),
            'context'  => $announcement->audienceLabel(),
            'author'   => $announcement->author?->name,
            'route'    => 'student.announcements.show',
            'params'   => ['announcement' => $announcement->id],
            'action'   => 'Read announcement',
            'details'  => array_filter([
                'For'       => $announcement->audienceLabel(),
                'Posted by' => $announcement->author?->name,
                'Until'     => $announcement->expires_at?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
            ]),
        ]);
    }

    protected function mailSubject(): string
    {
        return '[EduVers] '.$this->data['title'];
    }

    protected function mailIntro(): string
    {
        return self::md(($this->data['author'] ?? 'EduVers').' posted an announcement for '.$this->data['context'].'.');
    }
}
