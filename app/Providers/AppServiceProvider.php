<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

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
    }
}
