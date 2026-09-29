<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class LoginController extends Controller
{
    /** Max failed attempts per email + IP before a lockout. */
    private const MAX_ATTEMPTS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many sign-in attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // Back to the page they were trying to open, but only if their role may open it.
        // (A developer link left over from an earlier session must not 403 a teacher.)
        $intended = $request->session()->pull('url.intended');

        return redirect()->to(
            $this->isAllowedFor($request->user(), $intended) ? $intended : route('dashboard')
        );
    }

    /** Whether $url is a page of this app that the user's role:… guard lets them open. */
    private function isAllowedFor(User $user, mixed $url): bool
    {
        if (! is_string($url) || $url === '' || ! str_starts_with($url, url('/'))) {
            return false;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($url));
        } catch (Throwable) {
            return false; // unknown page
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
                if (! $user->hasRole(...explode(',', substr($middleware, 5)))) {
                    return false;
                }
            }
        }

        return true;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }
}
