<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // MAIL_MAILER=brevo — email over HTTPS for hosts that block SMTP (e.g. Railway Hobby)
        Mail::extend('brevo', fn (array $config = []) => new BrevoApiTransport((string) ($config['key'] ?? '')));

        // "Forgot password" email in the EduVers theme
        ResetPassword::toMailUsing(fn (object $user, string $token) => (new MailMessage)
            ->theme('eduvers')
            ->subject('[EduVers] Reset your password')
            ->greeting('Hello '.(Str::before(trim((string) $user->name), ' ') ?: 'there').',')
            ->line('Someone asked to reset the password of your EduVers account. Click the button to choose a new one.')
            ->action('Choose a new password', route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]))
            ->line('The link works once, for 60 minutes.')
            ->line('If you did not ask for this, you can ignore this email. Your password stays the same.'));
    }
}
