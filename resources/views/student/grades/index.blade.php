<x-layouts.app title="My Grades">
    <x-page-header eyebrow="Progress" title="My grades" description="All your quiz results, grouped by term and subject." />

    <div class="mt-8 grid gap-5 sm:grid-cols-3">
        <x-stat-card label="Quizzes taken" :value="$summary['taken']" icon="clipboard" />
        <x-stat-card label="Passed" :value="$summary['passed']" icon="check-circle" :hint="$summary['taken'] ? round($summary['passed'] / $summary['taken'] * 100).'% pass rate' : null" />
        <x-stat-card label="Overall average" :value="$summary['average'] !== null ? $summary['average'].'%' : '—'" icon="chart" />
    </div>

    @forelse ($byTerm as $termName => $subjects)
        <section class="mt-10">
            <h2 class="font-serif text-2xl font-semibold text-ink-900">{{ $termName }}</h2>
            <div class="mt-4 grid gap-6 lg:grid-cols-2">
                @foreach ($subjects as $subjectName => $rows)
                    @php $avg = (int) round($rows->avg(fn ($s) => $s->percent())); @endphp
                    <div class="card">
                        <div class="flex items-center justify-between border-b border-ivory-300 px-6 py-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-champagne-dark">{{ $rows->first()->quiz->subject->code }}</p>
                                <h3 class="font-serif text-lg font-semibold text-ink-900">{{ $subjectName }}</h3>
                            </div>
                            <div class="text-right">
                                <p class="font-serif text-2xl font-semibold text-ink-900">{{ $avg }}%</p>
                                <p class="text-xs text-slate-400">average</p>
                            </div>
                        </div>
                        <ul class="divide-y divide-ivory-200">
                            @foreach ($rows as $submission)
                                <li>
                                    <a href="{{ route('student.quizzes.show', $submission->quiz) }}" class="flex items-center gap-4 px-6 py-3.5 transition hover:bg-ivory-50">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-medium text-ink-900">{{ $submission->quiz->title }}</p>
                                            <p class="text-xs text-slate-500">{{ (float) $submission->score }}/{{ $submission->total_items }} · {{ $submission->created_at?->format('M j, Y') }}</p>
                                        </div>
                                        <div class="hidden w-24 sm:block">
                                            <div class="h-1.5 overflow-hidden rounded-full bg-ivory-200">
                                                <div class="h-full rounded-full {{ $submission->passed ? 'bg-gold-gradient' : 'bg-red-400' }}" style="width: {{ $submission->percent() }}%"></div>
                                            </div>
                                        </div>
                                        <span class="{{ $submission->passed ? 'badge-gold' : 'badge-slate' }} w-14 justify-center tabular-nums">{{ $submission->percent() }}%</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="card mt-8">
            <x-empty-state icon="chart" title="No grades yet" message="Take a quiz and your result will appear here." />
        </div>
    @endforelse
</x-layouts.app>
