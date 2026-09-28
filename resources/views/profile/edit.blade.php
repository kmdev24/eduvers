<x-layouts.app title="My Profile">
    <x-page-header eyebrow="Account" title="My profile" description="Update your name and keep your password secure." />

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        {{-- Identity card --}}
        <aside class="relative h-fit overflow-hidden rounded-2xl bg-ink-gradient p-8 text-center text-ivory shadow-elevated">
            <div class="pointer-events-none absolute -top-20 left-1/2 size-64 -translate-x-1/2 rounded-full bg-gold-500/20 blur-3xl"></div>
            <span class="relative mx-auto flex size-24 items-center justify-center rounded-full bg-gold-gradient font-serif text-3xl font-semibold text-ink-900 shadow-gold ring-4 ring-white/10">
                {{ $user->initials() }}
            </span>
            <h2 class="relative mt-5 font-serif text-2xl font-semibold">{{ $user->name }}</h2>
            <p class="relative text-sm text-slate-300">{{ $user->email }}</p>
            <div class="relative mt-5 flex flex-wrap justify-center gap-2 text-xs">
                <span class="rounded-full bg-gold-500/20 px-3 py-1 font-medium text-gold-300 ring-1 ring-gold-500/30">{{ $user->role->label() }}</span>
                @if ($user->teacher_type)
                    <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/10">{{ $user->teacher_type->label() }}</span>
                @endif
                @if ($user->section)
                    <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/10">{{ $user->section->name }} · {{ $user->section->gradeLevel->name }}</span>
                @endif
            </div>
            <div class="relative mt-6 h-px bg-gradient-to-r from-transparent via-gold-500/40 to-transparent"></div>
            <p class="relative mt-5 text-xs text-slate-400">Member since {{ $user->created_at?->format('F Y') }}</p>
        </aside>

        <div class="space-y-6 xl:col-span-2">
            {{-- Account details --}}
            <form method="POST" action="{{ route('profile.update') }}" class="card space-y-5 p-6 sm:p-8">
                @csrf
                @method('PUT')
                <div>
                    <h2 class="font-serif text-lg font-semibold">Account details</h2>
                    <p class="text-sm text-slate-500">This is how your name appears to teachers, students and in reports.</p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field label="Full name" name="name" bag="profile">
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="255" class="input">
                    </x-field>
                    <x-field label="Email address" for="email" hint="Contact the EduVers administrator to change your email.">
                        <input id="email" type="email" value="{{ $user->email }}" disabled class="input cursor-not-allowed bg-ivory-200 text-slate-500">
                    </x-field>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-dark">Save name</button>
                </div>
            </form>

            {{-- Password --}}
            <form method="POST" action="{{ route('profile.password') }}" class="card-gold space-y-5 p-6 sm:p-8">
                @csrf
                @method('PUT')
                <div>
                    <h2 class="font-serif text-lg font-semibold">Change password</h2>
                    <p class="text-sm text-slate-500">Use at least 8 characters with both letters and numbers.</p>
                </div>

                <x-field label="Current password" name="current_password" bag="password">
                    <div class="relative">
                        <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="input pr-12">
                        <button type="button" data-toggle-password="current_password" class="icon-btn absolute top-1/2 right-1.5 -translate-y-1/2" aria-label="Show or hide password"><x-icon name="eye" class="size-4" /></button>
                    </div>
                </x-field>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field label="New password" name="password" bag="password">
                        <div class="relative">
                            <input id="password" name="password" type="password" required autocomplete="new-password" class="input pr-12">
                            <button type="button" data-toggle-password="password" class="icon-btn absolute top-1/2 right-1.5 -translate-y-1/2" aria-label="Show or hide password"><x-icon name="eye" class="size-4" /></button>
                        </div>
                    </x-field>
                    <x-field label="Confirm new password" name="password_confirmation" bag="password">
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
                    </x-field>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-gold"><x-icon name="lock" class="size-4" /> Update password</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
