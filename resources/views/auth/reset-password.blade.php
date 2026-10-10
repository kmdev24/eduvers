<x-layouts.guest title="Choose a new password">
<section class="flex min-h-full items-center justify-center px-6 py-12 sm:px-12">
    <div class="w-full max-w-md">
        <div class="mb-10"><x-brand /></div>

        <p class="text-xs font-semibold uppercase tracking-luxe text-champagne-dark">Account recovery</p>
        <h1 class="mt-3 font-serif text-4xl font-semibold text-ink-900">Choose a new password</h1>
        <p class="mt-3 text-sm text-slate-500">At least 8 characters, with letters and numbers.</p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-ink-800">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username"
                       @class(['input py-3', 'border-red-400 focus:border-red-500 focus:ring-red-500/20' => $errors->has('email')])>
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-ink-800">New password</label>
                <input id="password" name="password" type="password" required autofocus autocomplete="new-password"
                       @class(['input py-3', 'border-red-400 focus:border-red-500 focus:ring-red-500/20' => $errors->has('password')])>
                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-medium text-ink-800">Type it again</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input py-3">
            </div>

            <button type="submit" class="btn-gold w-full py-3.5 text-base">
                Save new password
                <x-icon name="arrow-right" class="size-4" />
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" class="font-medium text-gold-700 hover:text-gold-900">Back to sign in</a>
        </p>
    </div>
</section>
</x-layouts.guest>
