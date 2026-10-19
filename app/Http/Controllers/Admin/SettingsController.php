<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        // Keys we allow the developer to change
        $keys = [
            'brevo' => [
                'MAIL_MAILER'        => config('mail.default'),
                'BREVO_API_KEY'      => config('mail.mailers.brevo.key', ''),
                'MAIL_FROM_ADDRESS'  => config('mail.from.address'),
                'MAIL_FROM_NAME'     => config('mail.from.name'),
            ],
            'assistant' => [
                'ASSISTANT_AI_KEY'    => config('assistant.ai.key', ''),
                'ASSISTANT_AI_MODEL'  => config('assistant.ai.model', 'gemini-2.0-flash'),
            ],
        ];

        return view('admin.settings', compact('keys'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'BREVO_API_KEY'       => 'nullable|string',
            'MAIL_MAILER'         => 'required|in:log,smtp,brevo',
            'MAIL_FROM_ADDRESS'   => 'required|email',
            'MAIL_FROM_NAME'      => 'nullable|string|max:100',
            'ASSISTANT_AI_KEY'    => 'nullable|string',
            'ASSISTANT_AI_MODEL'  => 'nullable|string',
        ]);

        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return back()->with('error', '.env file not found — cannot save settings.');
        }

        $content = File::get($envPath);

        foreach ($validated as $key => $value) {
            // Replace the line; if missing, append it
            $pattern = "/^{$key}=.*/m";
            $newLine = "{$key}={$value}";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $newLine, $content, 1);
            } else {
                // Append to the end
                $content .= "\n{$newLine}";
            }
        }

        File::put($envPath, $content);

        // Clear config cache so the new values are picked up immediately
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return back()->with('status', 'Settings saved. The changes take effect on the next page load.');
    }
}
