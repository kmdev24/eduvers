<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

/** "Forgot password": email a one-time reset link (valid 60 minutes), then set a new password. */
class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        try {
            Password::sendResetLink($request->only('email'));
        } catch (Throwable $e) {
            report($e); // mail is down: logged, but the answer below stays the same
        }

        // Same answer whether or not the account exists, so emails can't be probed
        return back()->with('status', 'If that email belongs to an EduVers account, a reset link is on its way. Check your inbox and spam folder. The link works for 60 minutes.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save(); // hashed by the model cast

            // Sign the account out everywhere else (someone else may have been using it)
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Your password was changed. Sign in with the new password.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'This reset link is invalid or has expired. Ask for a new one.']);
    }
}
