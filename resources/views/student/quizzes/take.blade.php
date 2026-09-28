<x-layouts.app :title="$quiz->title">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('student.quizzes.index') }}" class="text-sm font-medium text-gold-700 hover:text-gold-900">← Quizzes</a>

        <section class="relative mt-4 overflow-hidden rounded-2xl bg-ink-gradient p-8 text-ivory shadow-elevated">
            <div class="pointer-events-none absolute -top-20 -right-16 size-64 rounded-full bg-gold-500/20 blur-3xl"></div>
            <p class="relative text-xs font-semibold uppercase tracking-luxe text-gold-400">{{ $quiz->subject->code }} · {{ $quiz->subject->name }}</p>
            <h1 class="relative mt-2 font-serif text-3xl font-semibold">{{ $quiz->title }}</h1>
            @if ($quiz->description)
                <p class="relative mt-3 whitespace-pre-line text-slate-300">{{ $quiz->description }}</p>
            @endif
            <div class="relative mt-5 flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/10">{{ $quiz->questions->count() }} questions</span>
                <span class="rounded-full bg-gold-500/20 px-3 py-1 text-gold-300 ring-1 ring-gold-500/30">Pass mark {{ $quiz->passing_score }}%</span>
                <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/10">One attempt only</span>
            </div>
        </section>

        @if ($errors->any())
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                Please answer every question before submitting.
            </div>
        @endif

        <form method="POST" action="{{ route('student.quizzes.submit', $quiz) }}" class="mt-6 space-y-5"
              data-confirm="Submit your answers? You can only take this quiz once.">
            @csrf
            @foreach ($quiz->questions as $i => $question)
                <fieldset @class(['card p-6', 'ring-2 ring-red-200' => $errors->has('answers.'.$question->id)])>
                    <legend class="sr-only">Question {{ $i + 1 }}</legend>
                    <div class="flex items-start gap-3">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink-900 text-sm font-semibold text-ivory">{{ $i + 1 }}</span>
                        <p class="pt-1 font-medium whitespace-pre-line text-ink-900">{{ $question->question }}</p>
                    </div>
                    <div class="mt-4 space-y-2.5 sm:pl-11">
                        @foreach ($question->options_json as $key => $text)
                            <label class="block">
                                <input type="radio" name="answers[{{ $question->id }}]" value="{{ $key }}" class="peer sr-only"
                                       @checked(old('answers.'.$question->id) === $key)>
                                <span class="choice-card">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-ivory-200 text-sm font-semibold text-slate-600">{{ $key }}</span>
                                    <span class="text-ink-800">{{ $text }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('answers.'.$question->id)
                        <p class="mt-3 text-sm text-red-600 sm:pl-11">{{ $message }}</p>
                    @enderror
                </fieldset>
            @endforeach

            <div class="card-gold flex flex-col items-center justify-between gap-4 p-6 sm:flex-row">
                <p class="text-sm text-slate-600">Check your answers. Once submitted, your score is final.</p>
                <button type="submit" class="btn-gold px-8 py-3">Submit answers</button>
            </div>
        </form>
    </div>
</x-layouts.app>
