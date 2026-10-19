<x-layouts.guest title="Sign in">
<div class="grid min-h-full lg:grid-cols-2">

    {{-- ================= Brand panel (the landing screen on phones) ================= --}}
    <section class="relative flex min-h-screen flex-col justify-between overflow-hidden bg-ink-gradient p-8 sm:p-12 lg:min-h-0 lg:p-14">
        {{-- decorative glow + fine grid --}}
        <div class="pointer-events-none absolute -top-40 -right-40 size-[32rem] rounded-full bg-gold-500/15 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-48 -left-24 size-[28rem] rounded-full bg-champagne/10 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.04]"
             style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 48px 48px;"></div>

        <x-brand dark class="relative" />

        <div class="relative max-w-lg py-10 lg:py-0">
            <x-logo class="size-20 ring-4 sm:size-24" />
            <p class="mt-8 text-xs font-semibold uppercase tracking-luxe text-gold-400">Movers Institute of Technology and Education</p>
            <h1 class="mt-6 font-serif text-4xl font-semibold leading-[1.1] text-ivory sm:text-5xl">
                Where excellence is
                <span class="bg-gold-gradient bg-clip-text text-transparent">taught, earned,</span>
                and measured.
            </h1>
            <p class="mt-6 flex items-center gap-3 font-serif text-xl text-gold-300 italic sm:text-2xl">
                <span class="h-px w-10 bg-gold-500/60"></span>
                Transform, Learn, Lead
            </p>
            <p class="mt-6 text-base leading-relaxed text-slate-300">
                Lessons, quizzes and progress for Senior High School and College, all in one place.
            </p>

            {{-- On phones the form is the next screen down, so this is the way in --}}
            <a href="#sign-in" class="btn-gold mt-9 w-full py-3.5 text-base lg:hidden">
                Sign in
                <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>

        <div class="relative">
            <div class="mb-6 h-px bg-gradient-to-r from-gold-500/50 via-gold-500/20 to-transparent"></div>
            {{-- One per line on a phone, three across once there is room --}}
            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-6">
                @foreach ($offerings as $tile)
                    <div class="flex items-baseline justify-between gap-4 border-b border-white/5 pb-3 sm:block sm:border-0 sm:pb-0">
                        <dt class="text-[10px] font-semibold uppercase tracking-luxe text-slate-500">{{ $tile['label'] }}</dt>
                        <dd class="text-right sm:mt-1 sm:text-left">
                            <button type="button" data-dialog-open="offering-{{ $tile['key'] }}"
                                    class="group flex items-start gap-1.5 text-left font-serif text-lg text-ivory transition hover:text-gold-300 focus:rounded focus:ring-2 focus:ring-gold-500/50 focus:outline-none"
                                    aria-haspopup="dialog">
                                <span class="underline decoration-gold-500/40 decoration-dotted underline-offset-4 group-hover:decoration-gold-400">{{ $tile['value'] }}</span>
                                <x-icon name="plus" class="mt-1.5 size-3.5 shrink-0 text-gold-500/70 transition group-hover:text-gold-400" />
                            </button>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ================= Sign-in form ================= --}}
    <section id="sign-in" class="flex min-h-screen items-center justify-center px-6 py-12 sm:px-12 lg:min-h-0"
             @if ($errors->any() || session('status')) data-scroll-into-view @endif>
        <div class="w-full max-w-md">
            <div class="mb-10 lg:hidden">
                <a href="#" class="mb-6 inline-flex items-center gap-1.5 text-sm text-slate-500 transition hover:text-ink-900">
                    <x-icon name="arrow-right" class="size-4 rotate-180" /> Back
                </a>
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
                        {{-- Focused by app.js on wide screens only: on a phone this field is the
                             second screen down, and autofocus would scroll straight past the front
                             page and open the keyboard. --}}
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required data-focus-on-wide autocomplete="username"
                               placeholder="you@movers.edu.ph"
                               @class(['input py-3 pl-12', 'border-red-400 focus:border-red-500 focus:ring-red-500/20' => $errors->has('email')])>
                    </div>
                    @error('email')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label for="password" class="block text-sm font-medium text-ink-800">Password</label>
                        <a href="{{ route('password.request') }}" class="text-sm font-medium text-gold-700 hover:text-gold-900">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <x-icon name="lock" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                               placeholder="••••••••"
                               class="input py-3 pr-12 pl-12">
                        <button type="button" data-password-toggle="password"
                                aria-label="Show password" aria-pressed="false"
                                class="absolute top-1/2 right-2 -translate-y-1/2 rounded-lg p-2 text-slate-400 transition hover:text-ink-800 focus:ring-2 focus:ring-gold-500/20 focus:outline-none">
                            <x-icon name="eye" class="size-5" data-password-show />
                            <x-icon name="eye-slash" class="size-5 hidden" data-password-hide />
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-gold w-full py-3.5 text-base">
                    Sign in
                    <x-icon name="arrow-right" class="size-4" />
                </button>
            </form>

            <div class="mt-10 flex items-start gap-3 rounded-xl border border-ivory-300 bg-white/60 p-4 text-xs leading-relaxed text-slate-500">
                <x-icon name="shield" class="size-5 shrink-0 text-champagne" />
                <p>Need an account, or no longer have access to your email? Please contact the EduVers administrator or the Registrar's Office.</p>
            </div>
        </div>
    </section>
</div>

{{-- What the school offers. Outside the brand panel, which is hidden on small screens. --}}
@foreach ($offerings as $tile)
    <dialog id="offering-{{ $tile['key'] }}" aria-labelledby="offering-{{ $tile['key'] }}-title"
            class="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-2xl border border-ivory-300 bg-ivory p-0 text-ink-900 shadow-elevated backdrop:bg-ink-950/70 backdrop:backdrop-blur-sm">
        <div class="flex items-start justify-between gap-4 border-b border-ivory-300 px-6 py-5">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-luxe text-champagne-dark">{{ $tile['label'] }}</p>
                <h2 id="offering-{{ $tile['key'] }}-title" class="mt-1 font-serif text-2xl font-semibold">{{ $tile['title'] }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $tile['note'] }}</p>
            </div>
            <button type="button" data-dialog-close class="icon-btn shrink-0" aria-label="Close">
                <x-icon name="x" class="size-5" />
            </button>
        </div>

        @if (empty($tile['rows']))
            <p class="px-6 py-10 text-center text-sm text-slate-500">Nothing to show here yet.</p>
        @else
            <ul class="divide-y divide-ivory-200">
                @foreach ($tile['rows'] as $row)
                    <li class="flex flex-col gap-1 px-6 py-3 sm:flex-row sm:items-baseline sm:justify-between sm:gap-6">
                        <span class="font-medium">{{ $row['name'] }}</span>
                        <span class="text-sm text-slate-500 sm:text-right">{{ $row['detail'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="border-t border-ivory-300 bg-ivory-50/60 px-6 py-4 text-right">
            <button type="button" data-dialog-close class="btn-ghost">Close</button>
        </div>
    </dialog>
@endforeach
</x-layouts.guest>
