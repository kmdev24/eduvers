<x-layouts.guest title="Sign in">
<div class="grid min-h-full lg:grid-cols-2">

    {{-- ================= Brand panel ================= --}}
    <section class="relative hidden overflow-hidden bg-ink-gradient lg:flex lg:flex-col lg:justify-between lg:p-14">
        {{-- decorative glow + fine grid --}}
        <div class="pointer-events-none absolute -top-40 -right-40 size-[32rem] rounded-full bg-gold-500/15 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-48 -left-24 size-[28rem] rounded-full bg-champagne/10 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.04]"
             style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 48px 48px;"></div>

        <x-brand dark class="relative" />

        <div class="relative max-w-lg">
            <x-logo class="size-24 ring-4" />
            <p class="mt-8 text-xs font-semibold uppercase tracking-luxe text-gold-400">Movers Institute of Technology and Education</p>
            <h1 class="mt-6 font-serif text-5xl font-semibold leading-[1.1] text-ivory">
                Where excellence is
                <span class="bg-gold-gradient bg-clip-text text-transparent">taught, earned,</span>
                and measured.
            </h1>
            <p class="mt-6 flex items-center gap-3 font-serif text-2xl text-gold-300 italic">
                <span class="h-px w-10 bg-gold-500/60"></span>
                Transform, Learn, Lead
            </p>
            <p class="mt-6 text-base leading-relaxed text-slate-300">
                Lessons, quizzes and progress for Senior High School and College, all in one place.
            </p>
        </div>

        <div class="relative">
            <div class="mb-6 h-px bg-gradient-to-r from-gold-500/50 via-gold-500/20 to-transparent"></div>
            <dl class="grid grid-cols-3 gap-6">
                <div>
                    <dt class="text-[10px] font-semibold uppercase tracking-luxe text-slate-500">Senior High</dt>
                    <dd class="mt-1 font-serif text-lg text-ivory">3 Terms / Year</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-semibold uppercase tracking-luxe text-slate-500">College</dt>
                    <dd class="mt-1 font-serif text-lg text-ivory">HRS Bundle</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-semibold uppercase tracking-luxe text-slate-500">Tracks</dt>
                    <dd class="mt-1 font-serif text-lg text-ivory">TechPro &amp; Academic</dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- ================= Sign-in form ================= --}}
    <section class="flex items-center justify-center px-6 py-12 sm:px-12">
        <div class="w-full max-w-md">
            <div class="mb-10 lg:hidden">
                <x-brand />
                <p class="mt-3 font-serif text-lg text-champagne-dark italic">Transform, Learn, Lead</p>
            </div>

            <p class="text-xs font-semibold uppercase tracking-luxe text-champagne-dark">Welcome back</p>
            <h2 class="mt-3 font-serif text-4xl font-semibold text-ink-900">Sign in to EduVers</h2>
            <p class="mt-3 text-sm text-slate-500">Use the account provided by your institution.</p>

            @if (session('status'))
                <div class="mt-6 flex items-center gap-3 rounded-xl border border-gold-200 bg-gold-50 px-4 py-3 text-sm text-gold-900">
                    <x-icon name="check-circle" class="size-5 text-gold-600" />
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
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

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-ink-800">Password</label>
                    <div class="relative">
                        <x-icon name="lock" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                               placeholder="••••••••"
                               class="input py-3 pl-12">
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="size-4 rounded border-ivory-300 text-gold-600 focus:ring-gold-500/40">
                    Keep me signed in
                </label>

                <button type="submit" class="btn-gold w-full py-3.5 text-base">
                    Sign in
                    <x-icon name="arrow-right" class="size-4" />
                </button>
            </form>

            <div class="mt-10 flex items-start gap-3 rounded-xl border border-ivory-300 bg-white/60 p-4 text-xs leading-relaxed text-slate-500">
                <x-icon name="shield" class="size-5 shrink-0 text-champagne" />
                <p>Forgot your password or need an account? Please contact the EduVers administrator or the Registrar's Office.</p>
            </div>
        </div>
    </section>
</div>
</x-layouts.guest>
