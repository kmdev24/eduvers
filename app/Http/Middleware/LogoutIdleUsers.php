<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a user out after config('eduvers.idle_minutes') without activity, so an
 * unattended computer (a shared lab PC) does not stay signed in.
 */
class LogoutIdleUsers
{
    private const KEY = 'last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $minutes = (int) config('eduvers.idle_minutes');

        if ($minutes <= 0 || ! $request->user()) {
            return $next($request);
        }

        $last = (int) $request->session()->get(self::KEY, 0);

        if ($last > 0 && now()->timestamp - $last > $minutes * 60) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Signed out after inactivity.'], 401)
                : redirect()->guest(route('login'))->with('status', "You were signed out after {$minutes} minutes of inactivity.");
        }

        // The bell's background check is not the user doing something
        if (! $request->routeIs('notifications.count', 'notifications.dropdown')) {
            $request->session()->put(self::KEY, now()->timestamp);
        }

        return $next($request);
    }
}
