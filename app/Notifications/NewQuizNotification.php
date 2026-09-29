<?php

namespace App\Notifications;

use App\Models\Quiz;
use Illuminate\Support\Str;

class NewQuizNotification extends StudentActivityNotification
{
    public static function for(Quiz $quiz): self
    {
        $quiz->loadMissing(['subject', 'teacher'])->loadCount('questions');

        return new self([
            'kind'     => 'quiz',
            'icon'     => 'clipboard',
            'headline' => 'New quiz in '.$quiz->subject->code,
            'title'    => $quiz->title,
            'excerpt'  => Str::limit(trim(preg_replace('/\s+/', ' ', (string) $quiz->description)), 180)
                          ?: $quiz->questions_count.' '.Str::plural('question', $quiz->questions_count).' · passing score '.$quiz->passing_score.'%',
            'context'  => $quiz->subject->code.' · '.$quiz->subject->name,
            'author'   => $quiz->teacher?->name,
            'route'    => 'student.quizzes.show',
            'params'   => ['quiz' => $quiz->id],
            'action'   => 'Take the quiz',
            'details'  => array_filter([
                'Subject'       => $quiz->subject->code.' – '.$quiz->subject->name,
                'Teacher'       => $quiz->teacher?->name,
                'Questions'     => (string) $quiz->questions_count,
                'Passing score' => $quiz->passing_score.'%',
            ]),
        ]);
    }

    protected function mailSubject(): string
    {
        return '[EduVers] New quiz: '.$this->data['title'];
    }

    protected function mailIntro(): string
    {
        return self::md(($this->data['author'] ?? 'Your teacher').' published a quiz for '.$this->data['context'].'. You can take it once, so set aside some quiet time.');
    }
}
