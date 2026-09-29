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
use Illuminate\Support\Facades\Cache;
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
    /** Cache keys read by Developer → System check. */
    public const LAST_RUN_KEY   = 'eduvers:notifications:last-run';
    public const LAST_ERROR_KEY = 'eduvers:notifications:last-error';

    /** The error from the most recent notify call in this request (null = it worked). */
    public ?Throwable $lastError = null;

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

    /** Flash-message suffix for the teacher, e.g. " 32 students notified." */
    public function summary(int $count): string
    {
        if ($this->lastError) {
            return ' But students could not be notified. A developer can see why under System check.';
        }

        return $count > 0
            ? ' '.$count.' '.Str::plural('student', $count).' notified.'
            : ' No students were notified (no students match this audience).';
    }

    /**
     * @param  array<int>|null  $sectionIds  null = every student in the school
     */
    private function notifySections(StudentActivityNotification $notification, ?array $sectionIds): int
    {
        $this->lastError = null;
        $notified = 0;

        if ($sectionIds !== null && $sectionIds === []) {
            $this->remember($notification, $sectionIds, 0);

            return 0;
        }

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
            $this->lastError = $e;
        }

        $this->remember($notification, $sectionIds, $notified);

        return $notified;
    }

    /** Keep the last run (and last failure) so Developer → System check can show them. */
    private function remember(StudentActivityNotification $notification, ?array $sectionIds, int $notified): void
    {
        try {
            Cache::put(self::LAST_RUN_KEY, [
                'at'       => now()->toIso8601String(),
                'kind'     => $notification->data['kind'],
                'title'    => $notification->data['title'],
                'sections' => $sectionIds === null ? 'all students' : implode(', ', $sectionIds),
                'notified' => $notified,
                'error'    => $this->lastError?->getMessage(),
            ], now()->addDays(30));

            if ($this->lastError) {
                Cache::put(self::LAST_ERROR_KEY, [
                    'at'        => now()->toIso8601String(),
                    'title'     => $notification->data['title'],
                    'exception' => $this->lastError::class,
                    'message'   => $this->lastError->getMessage(),
                    'where'     => $this->lastError->getFile().':'.$this->lastError->getLine(),
                ], now()->addDays(30));
            }
        } catch (Throwable) {
            // Diagnostics only — never break posting
        }
    }
}
