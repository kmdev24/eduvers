<?php

namespace App\Notifications;

use App\Models\Lesson;

class NewLessonNotification extends StudentActivityNotification
{
    public static function for(Lesson $lesson): self
    {
        $lesson->loadMissing(['subject', 'teacher']);

        $includes = array_filter([
            $lesson->hasVideo() ? 'Video' : null,
            $lesson->hasAttachment() ? 'Attachment ('.$lesson->original_filename.')' : null,
        ]);

        return new self([
            'kind'     => 'lesson',
            'icon'     => 'book-open',
            'headline' => 'New lesson in '.$lesson->subject->code,
            'title'    => $lesson->title,
            'excerpt'  => $lesson->excerpt(180),
            'context'  => $lesson->subject->code.' · '.$lesson->subject->name,
            'author'   => $lesson->teacher?->name,
            'route'    => 'student.lessons.show',
            'params'   => ['lesson' => $lesson->id],
            'action'   => 'Open lesson',
            'details'  => array_filter([
                'Subject'  => $lesson->subject->code.' – '.$lesson->subject->name,
                'Teacher'  => $lesson->teacher?->name,
                'Includes' => $includes ? implode(', ', $includes) : null,
            ]),
        ]);
    }

    protected function mailSubject(): string
    {
        return '[EduVers] New lesson: '.$this->data['title'];
    }

    protected function mailIntro(): string
    {
        return self::md(($this->data['author'] ?? 'Your teacher').' posted a new lesson for '.$this->data['context'].'.');
    }
}
