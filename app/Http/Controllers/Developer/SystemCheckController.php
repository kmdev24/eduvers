<?php

namespace App\Http\Controllers\Developer;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\CourseAnnouncementNotification;
use App\Support\StudentNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Developer → System check: is the notification & email pipeline healthy?
 * Shows settings, queue state, recent notifications and the last error, and can
 * send a test notification that reports failures on screen instead of in the logs.
 */
class SystemCheckController extends Controller
{
    public function index(): View
    {
        $hasNotifications = Schema::hasTable('notifications');
        $hasJobs          = Schema::hasTable('jobs');
        $hasFailed        = Schema::hasTable('failed_jobs');

        $oldestJob = $hasJobs ? DB::table('jobs')->min('created_at') : null;

        return view('developer.system', [
            'settings' => [
                'Queue connection'   => config('queue.default'),
                'Queue worker'       => config('eduvers.notifications.queue_worker') ? 'Started with the container' : 'Off (RUN_QUEUE_WORKER=false)',
                'Mailer'             => config('mail.default'),
                'From address'       => config('mail.from.address'),
                'Brevo API key'      => filled(config('mail.mailers.brevo.key')) ? 'Set' : 'Not set',
                'Email alerts'       => config('eduvers.notifications.mail') ? 'On' : 'Off (NOTIFICATIONS_MAIL=false)',
                'App URL (in links)' => config('app.url'),
            ],
            'hasNotifications' => $hasNotifications,
            'notificationCount' => $hasNotifications ? DatabaseNotification::count() : null,
            'recent'           => $hasNotifications
                ? DatabaseNotification::query()->with('notifiable')->latest()->take(10)->get()
                : collect(),
            'pendingJobs'      => $hasJobs ? DB::table('jobs')->count() : null,
            'oldestJobAge'     => $oldestJob ? Carbon::createFromTimestamp((int) $oldestJob)->diffForHumans() : null,
            'workerStuck'      => $oldestJob && (now()->timestamp - (int) $oldestJob) > 120,
            'failedJobs'       => $hasFailed
                ? DB::table('failed_jobs')->latest('failed_at')->take(5)->get(['failed_at', 'exception'])
                    ->map(fn ($job) => ['at' => $job->failed_at, 'error' => Str::limit(strtok((string) $job->exception, "\n"), 300)])
                : collect(),
            'failedCount'      => $hasFailed ? DB::table('failed_jobs')->count() : null,
            'lastRun'          => $this->cached(StudentNotifier::LAST_RUN_KEY),
            'lastError'        => $this->cached(StudentNotifier::LAST_ERROR_KEY),
            'students'         => User::query()->where('role', UserRole::Student)->with('section')->orderBy('name')->limit(500)->get(),
        ]);
    }

    /** Send a test notification right now and show exactly what happened. */
    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('users', 'id')->where('role', UserRole::Student->value)],
            'channels'   => ['required', Rule::in(['database', 'both'])],
        ]);

        $student  = User::findOrFail($data['student_id']);
        $channels = $data['channels'] === 'both' ? ['database', 'mail'] : ['database'];
        $results  = [];

        $notification = new CourseAnnouncementNotification([
            'kind'     => 'announcement',
            'icon'     => 'megaphone',
            'headline' => 'Test notification',
            'title'    => 'EduVers notifications are working',
            'excerpt'  => 'This is a test sent from Developer → System check at '.now()->format('g:i A').'.',
            'context'  => 'System check',
            'author'   => $request->user()->name,
            'route'    => 'notifications.index',
            'params'   => [],
            'action'   => 'Open notifications',
        ]);

        foreach ($channels as $channel) {
            try {
                // sendNow: runs immediately (no queue) so any error shows up here
                Notification::sendNow($student, $notification, [$channel]);
                $results[] = ($channel === 'database' ? 'In-app' : 'Email to '.$student->email).': sent ✓';
            } catch (Throwable $e) {
                report($e);
                $results[] = ($channel === 'database' ? 'In-app' : 'Email').' FAILED: '.class_basename($e).' — '.$e->getMessage();
            }
        }

        $failed = collect($results)->contains(fn ($r) => str_contains($r, 'FAILED'));

        return back()->with($failed ? 'error' : 'status', 'Test for '.$student->name.' — '.implode(' | ', $results));
    }

    private function cached(string $key): ?array
    {
        try {
            return Cache::get($key);
        } catch (Throwable) {
            return null;
        }
    }
}
