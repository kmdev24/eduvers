<x-layouts.app title="Quizzes">
    <x-page-header eyebrow="Assessments" title="Quizzes"
                   description="Each quiz can be taken once. Your score appears as soon as you submit." />

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        {{-- To take --}}
        <section class="xl:col-span-2">
            <h2 class="mb-4 font-serif text-xl font-semibold">To take <span class="text-base font-normal text-slate-400">({{ $pending->count() }})</span></h2>
            @if ($pending->isEmpty())
                <div class="card"><x-empty-state icon="check-circle" title="You're all caught up" message="New quizzes from your teachers will appear here." /></div>
            @else
                <div class="grid gap-5 md:grid-cols-2">
                    @foreach ($pending as $quiz)
                        <article class="card flex flex-col p-6">
                            <p class="text-xs font-semibold uppercase tracking-wider text-champagne-dark">{{ $quiz->subject->code }} · {{ $quiz->subject->name }}</p>
                            <h3 class="mt-2 font-serif text-xl font-semibold leading-snug text-ink-900">{{ $quiz->title }}</h3>
                            @if ($quiz->description)
                                <p class="mt-2 line-clamp-2 text-sm text-slate-500">{{ $quiz->description }}</p>
                            @endif
                            <div class="mt-auto flex items-center justify-between pt-5">
                                <span class="text-xs text-slate-500">{{ $quiz->questions_count }} questions · pass {{ $quiz->passing_score }}%</span>
                                <a href="{{ route('student.quizzes.show', $quiz) }}" class="btn-gold px-4 py-2 text-xs">Start quiz <x-icon name="arrow-right" class="size-3.5" /></a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Completed --}}
        <section class="card h-fit">
            <div class="flex items-center justify-between border-b border-ivory-300 px-6 py-5">
                <h2 class="font-serif text-lg font-semibold">Completed</h2>
                <a href="{{ route('student.grades') }}" class="text-sm font-medium text-gold-700 hover:text-gold-900">My grades →</a>
            </div>
            <ul class="divide-y divide-ivory-200">
                @forelse ($completed as $quiz)
                    @php $sub = $submissions->get($quiz->id); @endphp
                    <li>
                        <a href="{{ route('student.quizzes.show', $quiz) }}" class="flex items-center gap-3 px-6 py-4 transition hover:bg-ivory-50">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-ink-900">{{ $quiz->title }}</p>
                                <p class="text-xs text-slate-500">{{ $quiz->subject->code }} · {{ $sub->created_at?->format('M j') }}</p>
                            </div>
                            <span class="{{ $sub->passed ? 'badge-gold' : 'badge-slate' }} tabular-nums">{{ $sub->percent() }}%</span>
                        </a>
                    </li>
                @empty
                    <li><x-empty-state icon="clipboard" title="No completed quizzes yet" /></li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.app>
