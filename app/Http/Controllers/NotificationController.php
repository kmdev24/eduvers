<?php

namespace App\Http\Controllers;

use App\Notifications\StudentActivityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in user's in-app notifications (Laravel "database" channel).
 * Every lookup goes through $request->user()->notifications(), so users can only
 * ever see or change their own.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user   = $request->user();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $notifications = ($filter === 'unread' ? $user->unreadNotifications() : $user->notifications())
            ->paginate(15)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter'        => $filter,
            'unreadCount'   => $user->unreadNotifications()->count(),
        ]);
    }

    /** Mark as read and jump to the lesson / quiz / announcement. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $url = StudentActivityNotification::urlFor($notification->data);

        return $url
            ? redirect()->to($url)
            : redirect()->route('notifications.index')->with('error', 'That item is no longer available.');
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }

    /**
     * Polled by the header bell (app.js): unread count plus the newest notification,
     * so the page can show a toast and refresh the dropdown when something new arrives.
     */
    public function count(Request $request): JsonResponse
    {
        $user   = $request->user();
        $latest = $user->notifications()->first();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $latest ? [
                'id'       => $latest->id,
                'unread'   => $latest->read_at === null,
                'headline' => $latest->data['headline'] ?? 'New notification',
                'title'    => $latest->data['title'] ?? '',
                'url'      => route('notifications.open', $latest->id),
            ] : null,
        ]);
    }

    /** Fresh inside of the bell dropdown (HTML), fetched when a new notification arrives. */
    public function dropdown(Request $request): View
    {
        $user = $request->user();

        return view('notifications._dropdown', [
            'unread' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()->take(6)->get(),
        ]);
    }
}
