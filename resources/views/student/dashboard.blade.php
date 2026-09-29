<x-layouts.app title="Student Dashboard">

    {{-- Hero --}}
    <section class="relative overflow-hidden rounded-2xl bg-ink-gradient p-8 text-ivory shadow-elevated sm:p-10">
        <div class="pointer-events-none absolute -top-24 -right-20 size-80 rounded-full bg-gold-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.035]"
             style="background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px); background-size: 40px 40px;"></div>

        <div class="relative">
            <p class="text-xs font-semibold uppercase tracking-luxe text-gold-400">Student portal</p>
            <h1 class="mt-3 font-serif text-3xl font-semibold sm:text-4xl">Hello, {{ strtok($student->name, ' ') }}.</h1>

            @if ($student->section)
                <p class="mt-2 max-w-xl text-slate-300">Keep going. Here's what's waiting for you this term.</p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-medium ring-1 ring-white/10">{{ $student->section->name }}</span>
                    <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-medium ring-1 ring-white/10">{{ $student->section->gradeLevel->name }}</span>
                    <span class="inline-flex items-center rounded-full bg-gold-500/20 px-3 py-1 text-xs font-medium text-gold-300 ring-1 ring-gold-500/30">{{ $student->section->trackStrand->name }}</span>
                </div>
            @else
                <p class="mt-2 max-w-xl text-slate-300">You're not enrolled in a section yet. Please contact the Registrar's Office so your subjects can appear here.</p>
            @endif
        </div>
    </section>

    {{-- Stats --}}
    <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Subjects" :value="$stats['subjects']" icon="stack" hint="In your section" />
        <x-stat-card label="Lessons" :value="$stats['lessons']" icon="book-open" hint="Available to read" />
        <x-stat-card label="Pending quizzes" :value="$stats['pending']" icon="clock" hint="Not yet taken" />
        <x-stat-card label="Average score" :value="$stats['average'] !== null ? $stats['average'].'%' : '—'" icon="chart" hint="Across submitted quizzes" />
    </div>

    {{-- Announcements --}}
    <x-announcement-feed :announcements="$announcements" class="mt-8" />

    <div class="mt-8 grid gap-6 xl:grid-cols-3">

        {{-- My subjects --}}
        <div class="card xl:col-span-2">
            <div class="border-b border-ivory-300 px-6 py-5">
                <h2 class="font-serif text-lg font-semibold">My subjects</h2>
                <p class="text-sm text-slate-500">Subjects and teachers for your section</p>
            </div>
            <ul class="divide-y divide-ivory-200">
                @forelse ($classes as $offering)
                    <li class="flex items-center gap-4 px-6 py-4 transition hover:bg-ivory-50">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gold-50 text-gold-600 ring-1 ring-gold-200">
                            <x-icon name="book-open" class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink-900">{{ $offering->subject->name }}</p>
                            <p class="text-sm text-slate-500">{{ $offering->subject->code }} · {{ $offering->teacher->name }}</p>
                        </div>
                    </li>
                @empty
                    <li><x-empty-state icon="stack" title="No subjects yet" message="Your subjects will appear once your section's schedule is set." /></li>
                @endforelse
            </ul>
        </div>

        {{-- Pending quizzes --}}
        <div class="card h-fit">
            <div class="border-b border-ivory-300 px-6 py-5">
                <h2 class="font-serif text-lg font-semibold">Quizzes to take</h2>
                <p class="text-sm text-slate-500">Newest first</p>
            </div>
            <ul class="divide-y divide-ivory-200">
                @forelse ($pendingQuizzes as $quiz)
                    <li class="px-6 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-champagne-dark">{{ $quiz->subject->code }}</p>
                        <a href="{{ route('student.quizzes.show', $quiz) }}" class="mt-1 block font-medium text-ink-900 hover:text-gold-700">{{ $quiz->title }}</a>
                    </li>
                @empty
                    <li><x-empty-state icon="check-circle" title="You're all caught up" message="No pending quizzes right now." /></li>
                @endforelse
            </ul>
        </div>

        {{-- Recent lessons --}}
        <div class="card xl:col-span-2">
            <div class="border-b border-ivory-300 px-6 py-5">
                <h2 class="font-serif text-lg font-semibold">New lessons</h2>
                <p class="text-sm text-slate-500">Recently posted by your teachers</p>
            </div>
            <ul class="divide-y divide-ivory-200">
                @forelse ($recentLessons as $lesson)
                    <li class="flex items-center justify-between gap-4 px-6 py-4">
                        <div class="min-w-0">
                            <a href="{{ route('student.lessons.show', $lesson) }}" class="block font-medium text-ink-900 hover:text-gold-700">{{ $lesson->title }}</a>
                            <p class="text-sm text-slate-500">{{ $lesson->subject->name }}</p>
                        </div>
                        <span class="shrink-0 text-xs text-slate-400">{{ $lesson->created_at?->diffForHumans() }}</span>
                    </li>
                @empty
                    <li><x-empty-state icon="book-open" title="No lessons posted yet" /></li>
                @endforelse
            </ul>
        </div>

        {{-- Recent scores --}}
        <div class="card h-fit">
            <div class="border-b border-ivory-300 px-6 py-5">
                <h2 class="font-serif text-lg font-semibold">Recent scores</h2>
                <p class="text-sm text-slate-500">Your latest quiz results</p>
            </div>
            <ul class="divide-y divide-ivory-200">
                @forelse ($recentScores as $submission)
                    @php
                        $percent = $submission->percent();
                    @endphp
                    <li class="flex items-center justify-between gap-3 px-6 py-4">
                        <div class="min-w-0">
                            <a href="{{ route('student.quizzes.show', $submission->quiz) }}" class="block text-sm font-medium text-ink-900 hover:text-gold-700">{{ $submission->quiz->title }}</a>
                            <p class="text-xs text-slate-500">{{ $submission->quiz->subject->name }}</p>
                        </div>
                        <span class="{{ $submission->passed ? 'badge-gold' : 'badge-slate' }} tabular-nums">{{ $percent }}%</span>
                    </li>
                @empty
                    <li><x-empty-state icon="chart" title="No scores yet" message="Take a quiz to see your results here." /></li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.app>
