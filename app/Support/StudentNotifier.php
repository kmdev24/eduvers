<?php

namespace App\Support;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\SubjectTeacher;
use App\Models\User;
use App\Notifications\CourseAnnouncementNotification;
use App\Notifications\NewLessonNotification;
use App\Notifications\NewQuizNotification;
use App\Notifications\StudentActivityNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Works out which students should hear about new course activity and notifies them
 * (in-app bell + queued email). Recipients match what each student can actually open:
 *
 *  - Lesson:       students whose section takes the lesson's subject (same rule as LessonPolicy)
 *  - Quiz:         students in the sections the quiz is published to
 *  - Announcement: students who can see it (all students, or one section)
 *
 * Every method returns how many students were notified. Failures are reported to the log
 * but never thrown, so a mail/queue problem can't stop a teacher from posting.
 */
class StudentNotifier
{
    public function lessonPosted(Lesson $lesson): int
    {
        $sectionIds = SubjectTeacher::query()
            ->where('subject_id', $lesson->subject_id)
            ->distinct()
            ->pluck('section_id')
            ->all();

        return $this->notifySections(NewLessonNotification::for($lesson), $sectionIds);
    }

    /**
     * @param  array<int>|null  $sectionIds  only these sections (e.g. ones just added to a
     *                                       published quiz); null = all of the quiz's sections
     */
    public function quizPublished(Quiz $quiz, ?array $sectionIds = null): int
    {
        if (! $quiz->is_published) {
            return 0;
        }

        $sectionIds ??= $quiz->sections()->pluck('sections.id')->all();

        return $this->notifySections(NewQuizNotification::for($quiz), $sectionIds);
    }

    public function announcementPosted(Announcement $announcement): int
    {
        return match ($announcement->audience) {
            AnnouncementAudience::Everyone,
            AnnouncementAudience::Students => $this->notifySections(CourseAnnouncementNotification::for($announcement), null),
            AnnouncementAudience::Section  => $this->notifySections(CourseAnnouncementNotification::for($announcement), [(int) $announcement->section_id]),
            AnnouncementAudience::Teachers => 0,
        };
    }

    /** Flash-message suffix, e.g. " 32 students notified." (empty when nobody was). */
    public static function summary(int $count): string
    {
        return $count > 0 ? ' '.$count.' '.Str::plural('student', $count).' notified.' : '';
    }

    /**
     * @param  array<int>|null  $sectionIds  null = every student in the school
     */
    private function notifySections(StudentActivityNotification $notification, ?array $sectionIds): int
    {
        if ($sectionIds !== null && $sectionIds === []) {
            return 0;
        }

        $notified = 0;

        try {
            User::query()
                ->students()
                ->when($sectionIds !== null, fn ($q) => $q->whereIn('section_id', $sectionIds))
                ->chunkById((int) config('eduvers.notifications.chunk', 200), function ($students) use ($notification, &$notified) {
                    Notification::send($students, $notification);
                    $notified += $students->count();
                });
        } catch (Throwable $e) {
            report($e);
        }

        return $notified;
    }
}
