<x-layouts.guest title="Forgot password">
<section class="flex min-h-full items-center justify-center px-6 py-12 sm:px-12">
    <div class="w-full max-w-md">
        <div class="mb-10"><x-brand /></div>

        <p class="text-xs font-semibold uppercase tracking-luxe text-champagne-dark">Account recovery</p>
        <h1 class="mt-3 font-serif text-4xl font-semibold text-ink-900">Forgot your password?</h1>
        <p class="mt-3 text-sm text-slate-500">Type the email address of your EduVers account. We will email you a link to choose a new password.</p>

        @if (session('status'))
            <div class="mt-6 flex items-start gap-3 rounded-xl border border-gold-200 bg-gold-50 px-4 py-3 text-sm text-gold-900" role="status">
                <x-icon name="check-circle" class="size-5 shrink-0 text-gold-600" />
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
            @csrf
            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-ink-800">Email address</label>
                <div class="relative">
                    <x-icon name="envelope" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           placeholder="you@movers.edu.ph"
                           @class(['input py-3 pl-12', 'border-red-400 focus:border-red-500 focus:ring-red-500/20' => $errors->has('email')])>
                </div>
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-gold w-full py-3.5 text-base">
                Email me a reset link
                <x-icon name="arrow-right" class="size-4" />
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-slate-500">
            Remembered it? <a href="{{ route('login') }}" class="font-medium text-gold-700 hover:text-gold-900">Back to sign in</a>
        </p>
    </div>
</section>
</x-layouts.guest>
