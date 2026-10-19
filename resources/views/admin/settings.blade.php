<x-layouts.app title="API & Email Settings">
    <x-page-header eyebrow="Developer tools"
                   title="API & Email Settings"
                   description="Configure the Brevo email API and the student assistant AI. Only visible to the developer role." />

    <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-8 space-y-8">
        @csrf
        @method('PUT')

        {{-- Brevo email --}}
        <section class="card p-6 sm:p-8">
            <div class="mb-6">
                <h2 class="font-serif text-lg font-semibold">Brevo Email</h2>
                <p class="text-sm text-slate-500">
                    Brevo is a transactional email service (free tier: 300 emails/day). Get your API key from
                    <a href="https://app.brevo.com/settings/keys" target="_blank" class="font-medium underline">Brevo API & SMTP</a>,
                    verify a sender address, and paste the key here.
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Email API" name="MAIL_MAILER" hint="'brevo' uses their HTTPS API; 'log' writes emails to storage/logs/laravel.log.">
                    <select id="MAIL_MAILER" name="MAIL_MAILER" class="input" required>
                        <option value="log" @selected(old('MAIL_MAILER', $keys['brevo']['MAIL_MAILER']) === 'log')>log (no emails sent)</option>
                        <option value="brevo" @selected(old('MAIL_MAILER', $keys['brevo']['MAIL_MAILER']) === 'brevo')>brevo (API)</option>
                        <option value="smtp" @selected(old('MAIL_MAILER', $keys['brevo']['MAIL_MAILER']) === 'smtp')>smtp (gmail, mailtrap)</option>
                    </select>
                </x-field>
                <x-field label="Brevo API key" name="BREVO_API_KEY">
                    <input id="BREVO_API_KEY" name="BREVO_API_KEY" type="text" value="{{ old('BREVO_API_KEY', $keys['brevo']['BREVO_API_KEY']) }}" class="input" placeholder="xkeysib-...">
                </x-field>

                <x-field label="From address" name="MAIL_FROM_ADDRESS">
                    <input id="MAIL_FROM_ADDRESS" name="MAIL_FROM_ADDRESS" type="email" value="{{ old('MAIL_FROM_ADDRESS', $keys['brevo']['MAIL_FROM_ADDRESS']) }}" required class="input" placeholder="noreply@eduvers.test">
                </x-field>
                <x-field label="From name" name="MAIL_FROM_NAME">
                    <input id="MAIL_FROM_NAME" name="MAIL_FROM_NAME" type="text" value="{{ old('MAIL_FROM_NAME', $keys['brevo']['MAIL_FROM_NAME']) }}" class="input" placeholder="EduVers">
                </x-field>
            </div>

            <div class="mt-6 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-medium">Note:</p>
                <p class="mt-1">Changes to the email API take effect on the next page load. If you change from "log" to "brevo", test that emails work at Admin → System check.</p>
            </div>
        </section>

        {{-- AI Assistant --}}
        <section class="card p-6 sm:p-8">
            <div class="mb-6">
                <h2 class="font-serif text-lg font-semibold">Student Assistant AI</h2>
                <p class="text-sm text-slate-500">
                    The student help button uses keyword matching first; if the question doesn't match, it falls back to
                    Google Gemini (free tier). Get your key from
                    <a href="https://aistudio.google.com/" target="_blank" class="font-medium underline">Google AI Studio</a>.
                    Leave empty to disable the AI fallback.
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Gemini API key" name="ASSISTANT_AI_KEY">
                    <input id="ASSISTANT_AI_KEY" name="ASSISTANT_AI_KEY" type="text" value="{{ old('ASSISTANT_AI_KEY', $keys['assistant']['ASSISTANT_AI_KEY']) }}" class="input" placeholder="AIza...">
                </x-field>
                <x-field label="Model" name="ASSISTANT_AI_MODEL" hint="gemini-2.0-flash is free and fast.">
                    <input id="ASSISTANT_AI_MODEL" name="ASSISTANT_AI_MODEL" type="text" value="{{ old('ASSISTANT_AI_MODEL', $keys['assistant']['ASSISTANT_AI_MODEL']) }}" class="input" placeholder="gemini-2.0-flash">
                </x-field>
            </div>

            <div class="mt-6 rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-900">
                <p class="font-medium">Remember:</p>
                <p class="mt-1">The student assistant is limited to answering questions about how to use EduVers. It will not answer anything else.</p>
            </div>
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.dashboard') }}" class="btn-ghost">Cancel</a>
            <button type="submit" class="btn-gold">Save settings</button>
        </div>
    </form>
</x-layouts.app>
