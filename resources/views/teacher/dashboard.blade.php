<x-layouts.app title="Teacher Dashboard">
    @php $teacher = auth()->user(); @endphp

    {{-- Heading --}}
    <div>
        <p class="eyebrow">
            Faculty @if ($teacher->teacher_type) · {{ $teacher->teacher_type->label() }} @endif
        </p>
        <h1 class="heading-serif mt-2 text-3xl sm:text-4xl">Welcome back, {{ strtok($teacher->name, ' ') }}.</h1>
        <p class="mt-2 text-slate-500">Your classes, content and the latest quiz results.</p>
    </div>

    {{-- Stats --}}
    <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Classes" :value="$stats['classes']" icon="building" hint="Subject × section assignments" />
        <x-stat-card label="Students" :value="$stats['students']" icon="users" hint="Across all your sections" />
        <x-stat-card label="Lessons" :value="$stats['lessons']" icon="book-open" hint="Published in your subjects" />
        <x-stat-card label="Quizzes" :value="$stats['quizzes']" icon="clipboard" hint="Created in your subjects" />
    </div>

    {{-- Announcements --}}
    <x-announcement-feed :announcements="$announcements" class="mt-8" />

    <div class="mt-8 grid gap-6 xl:grid-cols-3">

        {{-- My classes --}}
        <div class="xl:col-span-2">
            <div class="mb-4 flex items-end justify-between">
                <div>
                    <h2 class="font-serif text-xl font-semibold">My classes</h2>
                    <p class="text-sm text-slate-500">Your teaching load this academic year</p>
                </div>
            </div>

            @if ($assignments->isEmpty())
                <div class="card">
                    <x-empty-state icon="building" title="No classes assigned yet" message="Once the administrator assigns you to a subject and section, it will appear here." />
                </div>
            @else
                <div class="grid gap-5 md:grid-cols-2">
                    @foreach ($assignments as $offering)
                        <article class="card group relative overflow-hidden p-6 transition duration-300 hover:-translate-y-0.5 hover:shadow-elevated">
                            <div class="absolute inset-y-0 left-0 w-1 bg-gold-gradient opacity-70 transition group-hover:opacity-100"></div>
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-xs font-semibold uppercase tracking-luxe text-champagne-dark">{{ $offering->subject->code }}</p>
                                @if ($offering->subject->academicTerm)
                                    <span class="badge-gold">{{ $offering->subject->academicTerm->name }}</span>
                                @endif
                            </div>
                            <h3 class="mt-2 font-serif text-xl font-semibold leading-snug text-ink-900">{{ $offering->subject->name }}</h3>

                            <div class="gold-divider my-5"></div>

                            <dl class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt class="text-xs text-slate-400">Section</dt>
                                    <dd class="mt-0.5 font-medium text-ink-800">{{ $offering->section->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-slate-400">Students</dt>
                                    <dd class="mt-0.5 font-medium tabular-nums text-ink-800">{{ (int) ($studentCounts[$offering->section_id] ?? 0) }} / {{ $offering->section->capacity }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-xs text-slate-400">Level &amp; strand</dt>
                                    <dd class="mt-0.5 text-ink-800">{{ $offering->section->gradeLevel->name }} · {{ $offering->section->trackStrand->name }}</dd>
                                </div>
                            </dl>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <a href="{{ route('teacher.gradebook', ['class' => $offering->id, 'term' => $offering->subject->academic_term_id]) }}" class="btn-ghost px-3 py-1.5 text-xs"><x-icon name="chart" class="size-3.5" /> Grades</a>
                                <a href="{{ route('sections.masterlist', $offering->section) }}" target="_blank" class="btn-ghost px-3 py-1.5 text-xs"><x-icon name="queue-list" class="size-3.5" /> Master list</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Recent submissions --}}
        <div class="card h-fit">
            <div class="border-b border-ivory-300 px-6 py-5">
                <h2 class="font-serif text-lg font-semibold">Latest submissions</h2>
                <p class="text-sm text-slate-500">Quiz results from your students</p>
            </div>
            <ul class="divide-y divide-ivory-200">
                @forelse ($recentSubmissions as $submission)
                    @php
                        $percent = $submission->percent();
                    @endphp
                    <li class="flex items-center gap-3 px-6 py-4">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-ivory-200 text-xs font-semibold text-ink-800">{{ $submission->student->initials() }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-ink-900">{{ $submission->student->name }}</p>
                            <p class="text-xs text-slate-500">{{ $submission->quiz->title }} · {{ $submission->created_at?->diffForHumans() }}</p>
                        </div>
                        <span class="{{ $submission->passed ? 'badge-gold' : 'badge-slate' }} tabular-nums">{{ $percent }}%</span>
                    </li>
                @empty
                    <li><x-empty-state icon="clipboard" title="No submissions yet" message="Results will show up here as students take your quizzes." /></li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.app>
