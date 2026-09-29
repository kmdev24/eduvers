<x-layouts.app :title="'Result · '.$quiz->title">
    @php $percent = $submission->percent(); @endphp
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('student.quizzes.index') }}" class="text-sm font-medium text-gold-700 hover:text-gold-900">← Quizzes</a>

        {{-- Score --}}
        <section class="relative mt-4 overflow-hidden rounded-2xl bg-ink-gradient p-8 text-center text-ivory shadow-elevated sm:p-10">
            <div class="pointer-events-none absolute -top-24 left-1/2 size-80 -translate-x-1/2 rounded-full bg-gold-500/20 blur-3xl"></div>
            <p class="relative text-xs font-semibold uppercase tracking-luxe text-gold-400">{{ $quiz->subject->code }} · {{ $quiz->title }}</p>

            <div class="relative mx-auto mt-6 flex size-40 items-center justify-center rounded-full"
                 style="background: conic-gradient({{ $submission->passed ? '#D4AF37' : '#f87171' }} {{ $percent * 3.6 }}deg, rgba(255,255,255,0.08) 0deg);">
                <div class="flex size-32 flex-col items-center justify-center rounded-full bg-ink-900">
                    <span class="font-serif text-4xl font-semibold">{{ $percent }}%</span>
                    <span class="text-xs text-slate-400">{{ (float) $submission->score }} / {{ $submission->total_items }}</span>
                </div>
            </div>

            <p class="relative mt-6">
                @if ($submission->passed)
                    <span class="inline-flex items-center gap-2 rounded-full bg-gold-500/20 px-4 py-1.5 text-sm font-semibold text-gold-300 ring-1 ring-gold-500/40">
                        <x-icon name="check-circle" class="size-4" /> Passed
                    </span>
                @else
                    <span class="inline-flex items-center gap-2 rounded-full bg-red-500/15 px-4 py-1.5 text-sm font-semibold text-red-300 ring-1 ring-red-400/30">
                        Below pass mark
                    </span>
                @endif
            </p>
            <p class="relative mt-3 text-sm text-slate-400">Pass mark {{ $quiz->passing_score }}% · Submitted {{ $submission->created_at?->format('M j, Y g:i A') }}</p>
        </section>

        {{-- Review --}}
        @if ($quiz->reveal_answers)
            <h2 class="mt-10 font-serif text-xl font-semibold">Review your answers</h2>
            <div class="mt-4 space-y-4">
                @foreach ($quiz->questions as $i => $question)
                    @php
                        $mine    = $submission->answerFor($question->id);
                        $correct = $question->isCorrect($mine);
                    @endphp
                    <article class="card p-6">
                        <div class="flex items-start gap-3">
                            <span @class(['flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold', 'bg-gold-gradient text-ink-900' => $correct, 'bg-red-100 text-red-700' => ! $correct])>{{ $i + 1 }}</span>
                            <p class="pt-1 font-medium whitespace-pre-line text-ink-900">{{ $question->question }}</p>
                        </div>
                        <ul class="mt-4 space-y-2 sm:pl-11">
                            @foreach ($question->options_json as $key => $text)
                                @php
                                    $isAnswer = $key === $question->correct_answer;
                                    $isMine   = $key === $mine;
                                @endphp
                                <li @class([
                                    'flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm',
                                    'border-gold-400 bg-gold-50 text-ink-900' => $isAnswer,
                                    'border-red-300 bg-red-50 text-red-800' => $isMine && ! $isAnswer,
                                    'border-ivory-300 text-slate-500' => ! $isAnswer && ! $isMine,
                                ])>
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-white/70 text-xs font-semibold ring-1 ring-inset ring-current/20">{{ $key }}</span>
                                    <span class="flex-1">{{ $text }}</span>
                                    @if ($isAnswer) <span class="text-xs font-semibold text-gold-700">Correct answer</span>
                                    @elseif ($isMine) <span class="text-xs font-semibold">Your answer</span> @endif
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        @else
            <div class="card mt-8 p-6 text-center text-sm text-slate-500">
                Your teacher has chosen not to show the correct answers for this quiz.
            </div>
        @endif

        <div class="mt-8 flex justify-center gap-3">
            <a href="{{ route('student.grades') }}" class="btn-dark">View my grades</a>
            <a href="{{ route('student.quizzes.index') }}" class="btn-ghost">Back to quizzes</a>
        </div>
    </div>
</x-layouts.app>
