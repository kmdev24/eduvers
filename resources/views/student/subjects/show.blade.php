<x-layouts.app :title="$subject->name">
    <section class="relative overflow-hidden rounded-2xl bg-ink-gradient p-8 text-ivory shadow-elevated sm:p-10">
        <div class="pointer-events-none absolute -top-24 -right-20 size-80 rounded-full bg-gold-500/20 blur-3xl"></div>
        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('student.subjects.index') }}" class="text-xs font-semibold uppercase tracking-luxe text-gold-400 hover:text-gold-300">← My subjects</a>
                <p class="mt-4 text-sm text-slate-300">{{ $subject->code }}</p>
                <h1 class="font-serif text-3xl font-semibold sm:text-4xl">{{ $subject->name }}</h1>
                <p class="mt-2 text-slate-300">{{ $subject->gradeLevel->name }} · {{ $subject->academicTerm->name }}@if ($teacher) · {{ $teacher->name }}@endif</p>
            </div>
            <div class="flex gap-6 text-center">
                <div><p class="font-serif text-3xl font-semibold text-gold-300">{{ $lessons->count() }}</p><p class="text-xs uppercase tracking-wider text-slate-400">Lessons</p></div>
                <div><p class="font-serif text-3xl font-semibold text-gold-300">{{ $quizzes->count() }}</p><p class="text-xs uppercase tracking-wider text-slate-400">Quizzes</p></div>
            </div>
        </div>
    </section>

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        {{-- Lessons --}}
        <div class="card xl:col-span-2">
            <div class="border-b border-ivory-300 px-6 py-5">
                <h2 class="font-serif text-lg font-semibold">Lessons</h2>
                <p class="text-sm text-slate-500">Newest first</p>
            </div>
            <ul class="divide-y divide-ivory-200">
                @forelse ($lessons as $lesson)
                    <li>
                        <a href="{{ route('student.lessons.show', $lesson) }}" class="group flex items-start gap-4 px-6 py-5 transition hover:bg-ivory-50">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gold-50 text-gold-600 ring-1 ring-gold-200">
                                <x-icon name="book-open" class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink-900 group-hover:text-gold-800">{{ $lesson->title }}</p>
                                <p class="mt-0.5 line-clamp-2 text-sm text-slate-500">{{ $lesson->excerpt(180) ?: ($lesson->hasVideo() ? 'Watch the video lesson.' : 'Open to view the attached file.') }}</p>
                                <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                                    <span>{{ $lesson->created_at?->format('M j, Y') }}</span>
                                    @if ($lesson->hasVideo())
                                        <span class="badge-slate"><x-icon name="play" class="mr-1 size-3" /> Video</span>
                                    @endif
                                    @if ($lesson->hasAttachment())
                                        <span class="badge-gold">{{ $lesson->isPdf() ? 'PDF' : 'File' }}</span>
                                    @endif
                                </p>
                            </div>
                            <x-icon name="arrow-right" class="mt-3 size-4 shrink-0 text-slate-300 group-hover:text-gold-600" />
                        </a>
                    </li>
                @empty
                    <li><x-empty-state icon="book-open" title="No lessons posted yet" message="Your teacher hasn't posted lessons for this subject." /></li>
                @endforelse
            </ul>
        </div>

        {{-- Quizzes --}}
        <div class="card h-fit">
            <div class="border-b border-ivory-300 px-6 py-5">
                <h2 class="font-serif text-lg font-semibold">Quizzes</h2>
                <p class="text-sm text-slate-500">Published to your section</p>
            </div>
            <ul class="divide-y divide-ivory-200">
                @forelse ($quizzes as $quiz)
                    @php $sub = $submissions->get($quiz->id); @endphp
                    <li class="flex items-center gap-3 px-6 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-ink-900">{{ $quiz->title }}</p>
                            <p class="text-xs text-slate-500">{{ $quiz->questions_count }} questions · pass {{ $quiz->passing_score }}%</p>
                        </div>
                        @if ($sub)
                            <a href="{{ route('student.quizzes.show', $quiz) }}" class="{{ $sub->passed ? 'badge-gold' : 'badge-slate' }} tabular-nums">{{ $sub->percent() }}%</a>
                        @else
                            <a href="{{ route('student.quizzes.show', $quiz) }}" class="btn-gold px-3 py-1.5 text-xs">Start</a>
                        @endif
                    </li>
                @empty
                    <li><x-empty-state icon="clipboard" title="No quizzes yet" /></li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.app>
