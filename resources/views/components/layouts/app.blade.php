@props(['title' => 'Dashboard'])

@use('App\Enums\UserRole')
@use('App\Models\AcademicTerm')

@php
    $user        = auth()->user();
    $currentTerm = AcademicTerm::getCurrent();

    // Sidebar menu per role. 'route' => null shows the item as "Soon".
    $nav = match ($user->role) {
        UserRole::Developer => [
            ['label' => 'Dashboard',        'icon' => 'home',         'route' => 'developer.dashboard'],
            ['label' => 'Users',            'icon' => 'users',        'route' => 'developer.users.index',    'match' => 'developer.users.*'],
            ['label' => 'Sections',         'icon' => 'building',     'route' => 'developer.sections.index', 'match' => 'developer.sections.*'],
            ['label' => 'Subjects',         'icon' => 'stack',        'route' => 'developer.subjects.index', 'match' => 'developer.subjects.*'],
            ['label' => 'Academic Terms',   'icon' => 'calendar',     'route' => 'developer.terms.index',    'match' => 'developer.terms.*'],
            ['label' => 'Tracks & Strands', 'icon' => 'academic-cap', 'route' => 'developer.tracks.index',   'match' => 'developer.tracks.*'],
            ['label' => 'Announcements',    'icon' => 'megaphone',    'route' => 'announcements.index',      'match' => 'announcements.*'],
            ['label' => 'System check',     'icon' => 'shield',       'route' => 'developer.system',         'match' => 'developer.system*'],
        ],
        UserRole::Teacher => [
            ['label' => 'Dashboard', 'icon' => 'home',      'route' => 'teacher.dashboard'],
            ['label' => 'Lessons',   'icon' => 'book-open', 'route' => 'teacher.lessons.index', 'match' => 'teacher.lessons.*'],
            ['label' => 'Quizzes',   'icon' => 'clipboard', 'route' => 'teacher.quizzes.index', 'match' => 'teacher.quizzes.*'],
            ['label' => 'Gradebook',     'icon' => 'chart',     'route' => 'teacher.gradebook', 'match' => 'teacher.gradebook*'],
            ['label' => 'Announcements', 'icon' => 'megaphone', 'route' => 'announcements.index', 'match' => 'announcements.*'],
        ],
        UserRole::Student => [
            ['label' => 'Dashboard',   'icon' => 'home',      'route' => 'student.dashboard'],
            ['label' => 'My Subjects', 'icon' => 'stack',     'route' => 'student.subjects.index', 'match' => ['student.subjects.*', 'student.lessons.*']],
            ['label' => 'Quizzes',     'icon' => 'clipboard', 'route' => 'student.quizzes.index',  'match' => 'student.quizzes.*'],
            ['label' => 'My Grades',   'icon' => 'chart',     'route' => 'student.grades'],
            ['label' => 'Notifications', 'icon' => 'bell',    'route' => 'notifications.index', 'match' => 'notifications.*'],
        ],
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} · EduVers</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-ivory font-sans text-ink-900 antialiased">

{{-- Mobile backdrop --}}
<div data-sidebar-overlay class="fixed inset-0 z-40 hidden bg-ink-950/60 backdrop-blur-sm lg:hidden"></div>

{{-- ================= Sidebar ================= --}}
<aside data-sidebar
       class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-ink-gradient transition-transform duration-300 ease-out lg:translate-x-0">

    {{-- soft gold glow --}}
    <div class="pointer-events-none absolute -top-24 -left-24 size-72 rounded-full bg-gold-500/10 blur-3xl"></div>

    <div class="relative flex h-20 items-center justify-between px-6">
        <a href="{{ route('dashboard') }}"><x-brand dark /></a>
        <button type="button" data-sidebar-close class="rounded-lg p-1.5 text-slate-400 hover:bg-white/5 hover:text-white lg:hidden" aria-label="Close menu">
            <x-icon name="x" class="size-5" />
        </button>
    </div>

    <div class="relative mx-6 h-px bg-gradient-to-r from-transparent via-gold-500/40 to-transparent"></div>

    <nav class="relative flex-1 space-y-1 overflow-y-auto px-6 py-6">
        <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-luxe text-slate-500">
            {{ $user->role->label() }}
        </p>
        @foreach ($nav as $item)
            <x-nav-link :icon="$item['icon']" :route="$item['route']" :match="$item['match'] ?? null">{{ $item['label'] }}</x-nav-link>
        @endforeach

        <p class="mt-8 mb-3 px-3 text-[10px] font-semibold uppercase tracking-luxe text-slate-500">Account</p>
        <x-nav-link icon="user-circle" route="profile.edit">My Profile</x-nav-link>
    </nav>

    {{-- Signed-in user --}}
    <div class="relative border-t border-white/5 p-4">
        <div class="flex items-center gap-3 rounded-xl bg-white/[0.04] p-3 ring-1 ring-white/5">
            <a href="{{ route('profile.edit') }}" class="flex min-w-0 flex-1 items-center gap-3" title="My profile">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gold-gradient text-sm font-semibold text-ink-900">
                    {{ $user->initials() }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-medium text-ivory">{{ $user->name }}</span>
                    <span class="block text-xs break-all text-slate-400">{{ $user->email }}</span>
                </span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-lg p-2 text-slate-400 transition hover:bg-white/5 hover:text-gold-300" title="Sign out" aria-label="Sign out">
                    <x-icon name="logout" class="size-5" />
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- ================= Main ================= --}}
<div class="lg:pl-72">
    <header class="sticky top-0 z-30 border-b border-ivory-300/80 bg-ivory/85 backdrop-blur-md">
        <div class="flex min-h-20 items-center gap-3 px-4 py-3 sm:gap-4 sm:px-6 lg:px-10">
            <button type="button" data-sidebar-open class="-ml-1 rounded-lg p-2 text-ink-800 hover:bg-ivory-200 lg:hidden" aria-label="Open menu">
                <x-icon name="menu" class="size-6" />
            </button>
            <a href="{{ route('dashboard') }}" class="shrink-0 lg:hidden" aria-label="EduVers home"><x-logo class="size-10" /></a>

            <div class="min-w-0 flex-1">
                {{-- Live date & time in Philippine time (app.js updates it every second and makes it fit) --}}
                <p class="truncate text-xs font-semibold uppercase tracking-luxe text-champagne-dark" data-live-clock data-timezone="{{ config('app.timezone') }}" data-clock-show="date" data-clock-rotate="5">
                    <span data-clock-date>{{ now()->format('l, F j, Y') }}</span>
                    <span data-clock-sep class="mx-1 text-gold-400">·</span>
                    <span data-clock-time class="tabular-nums"><time>{{ now()->format('g:i:s A') }}</time> <span data-clock-zone class="text-slate-400">PHT</span></span>
                </p>
                {{-- Up to 2 lines so the sticky header stays compact; the full title is on the page itself --}}
                <p class="line-clamp-2 font-serif text-base leading-snug font-semibold text-ink-900 sm:text-xl" title="{{ $title }}">{{ $title }}</p>
            </div>

            <span class="hidden text-xs font-semibold uppercase tracking-luxe text-champagne-dark xl:inline">Transform · Learn · Lead</span>

            @if ($currentTerm)
                <span class="hidden items-center gap-2 rounded-full border border-gold-300/70 bg-white px-3.5 py-1.5 text-xs font-medium text-gold-800 shadow-soft sm:inline-flex">
                    <span class="size-1.5 rounded-full bg-gold-500"></span>
                    {{ $currentTerm->name }}@if ($currentTerm->academic_year) · {{ $currentTerm->academic_year }}@endif
                </span>
            @endif

            {{-- New lessons / quizzes / announcements --}}
            @if ($user->isStudent())
                <x-notification-bell :user="$user" />
            @endif
        </div>
    </header>

    <main class="px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
        @if (session('status'))
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-gold-200 bg-gold-50 px-4 py-3 text-sm text-gold-900">
                <x-icon name="check-circle" class="size-5 text-gold-600" />
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <x-icon name="shield" class="size-5 text-red-500" />
                {{ session('error') }}
            </div>
        @endif

        {{ $slot }}

        <footer class="mt-16 border-t border-ivory-300 pt-6 text-xs text-slate-400">
            © {{ now()->year }} Movers Institute of Technology and Education · EduVers Learning Management System
        </footer>
    </main>
</div>

</body>
</html>
