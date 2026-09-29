<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Base class for "something new was posted for you" notifications sent to students.
 *
 * Channels
 *  - database: the in-app bell. Runs on the `sync` connection, so it appears as soon as
 *              the teacher saves — even when no queue worker is running.
 *  - mail:     the email alert. Runs on the default queue connection (QUEUE_CONNECTION),
 *              so a background worker sends it and the teacher's page is not slowed down.
 *
 * Both are dispatched only after the surrounding database transaction commits
 * (ShouldQueueAfterCommit), so a rolled-back save never notifies anyone.
 *
 * The notification carries a plain array snapshot (no Eloquent models), so a queued
 * email still sends even if the lesson/quiz/announcement is edited or deleted meanwhile.
 */
abstract class StudentActivityNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    /** Retry a failed email (e.g. SMTP hiccup) up to 3 times. */
    public int $tries = 3;

    /** Drop the job quietly if the student account was deleted before it ran. */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  array{
     *     kind: string, icon: string, headline: string, title: string, excerpt: string,
     *     context: string, author: ?string, route: string, params: array<string, int>,
     *     action: string, details?: array<string, string>
     * }  $data
     */
    public function __construct(public readonly array $data) {}

    /** Seconds to wait before retrying a failed email. */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('eduvers.notifications.mail') && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** In-app notification immediately; email through the queue. */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /** Stored in notifications.data (JSON) and rendered by the bell. */
    public function toArray(object $notifiable): array
    {
        return $this->data;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->data;

        return (new MailMessage)
            ->subject($this->mailSubject())
            ->theme('eduvers')
            ->markdown('mail.student-activity', [
                'heading'   => $data['headline'],
                'firstName' => self::md(Str::before(trim((string) ($notifiable->name ?? '')), ' ') ?: 'there'),
                'intro'     => $this->mailIntro(),
                'title'     => self::md($data['title']),
                'excerpt'   => self::md($data['excerpt']),
                'details'   => collect($data['details'] ?? [])->map(fn ($v) => self::md((string) $v))->all(),
                'url'       => $this->url(),
                'action'    => $data['action'],
            ]);
    }

    /** Link to the lesson / quiz / announcement (null if the route no longer exists). */
    public function url(): string
    {
        return self::urlFor($this->data) ?? route('notifications.index');
    }

    /** Build the target URL from a stored payload (used by the bell and the emails). */
    public static function urlFor(array $data): ?string
    {
        $route = $data['route'] ?? null;

        if (! is_string($route) || ! Route::has($route)) {
            return null;
        }

        try {
            return route($route, $data['params'] ?? []);
        } catch (Throwable) {
            return null;
        }
    }

    abstract protected function mailSubject(): string;

    /** One sentence under the greeting, already Markdown-safe. */
    abstract protected function mailIntro(): string;

    /**
     * Make teacher-written text safe inside the Markdown email: collapse it to one line and
     * neutralise the syntax that could change its meaning (links/images and block starters
     * such as headings, quotes and lists). HTML is already escaped by Blade's {{ }}.
     */
    protected static function md(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $text = str_replace(['[', ']'], ['\\[', '\\]'], $text);
        $text = preg_replace('/^([#>+\-*=])/', '\\\\$1', $text);

        return preg_replace('/^(\d+)([.)])/', '$1\\\\$2', $text);
    }
}
