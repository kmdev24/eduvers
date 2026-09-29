<x-layouts.app title="Quiz Builder">
    <x-page-header :eyebrow="$quiz->subject->code.' · Quiz builder'" :title="$quiz->title">
        <x-slot:actions>
            <a href="{{ route('teacher.quizzes.index') }}" class="btn-ghost">All quizzes</a>
            <form method="POST" action="{{ route('teacher.quizzes.publish', $quiz) }}"
                  @if ($quiz->is_published) data-confirm="Unpublish this quiz? Students will no longer be able to start it." @endif>
                @csrf
                @method('PATCH')
                @if ($quiz->is_published)
                    <button type="submit" class="btn-dark">Unpublish</button>
                @else
                    <button type="submit" class="btn-gold"><x-icon name="check-circle" class="size-4" /> Publish quiz</button>
                @endif
            </form>
        </x-slot:actions>
    </x-page-header>

    {{-- Status strip --}}
    <div class="mt-6 flex flex-wrap items-center gap-3 text-sm">
        @if ($quiz->is_published)
            <span class="badge-gold">Published {{ $quiz->published_at?->diffForHumans() }}</span>
        @else
            <span class="badge-slate">Draft · not visible to students</span>
        @endif
        <span class="text-slate-500">{{ $quiz->questions->count() }} questions · pass mark {{ $quiz->passing_score }}% · {{ $quiz->submissions_count }} submissions</span>
    </div>

    @if ($locked)
        <div class="mt-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <x-icon name="lock" class="mt-0.5 size-4 shrink-0" />
            <p>Students have already taken this quiz, so its questions are locked to keep scores fair. You can still edit the title, instructions, sections and visibility.</p>
        </div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-3">

        {{-- Questions --}}
        <div class="space-y-6 xl:col-span-2" id="questions">
            @forelse ($quiz->questions as $i => $question)
                <article class="card p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink-900 text-sm font-semibold text-ivory">{{ $i + 1 }}</span>
                            <p class="pt-1 font-medium whitespace-pre-line text-ink-900">{{ $question->question }}</p>
                        </div>
                        @unless ($locked)
                            <div class="flex shrink-0 items-center gap-1">
                                <a href="{{ route('teacher.quizzes.questions.edit', [$quiz, $question]) }}" class="icon-btn" title="Edit question" aria-label="Edit question {{ $i + 1 }}"><x-icon name="pencil" class="size-4" /></a>
                                <form method="POST" action="{{ route('teacher.quizzes.questions.destroy', [$quiz, $question]) }}" data-confirm="Remove question {{ $i + 1 }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn-danger" title="Remove" aria-label="Remove question {{ $i + 1 }}"><x-icon name="trash" class="size-4" /></button>
                                </form>
                            </div>
                        @endunless
                    </div>
                    <ul class="mt-4 grid gap-2 pl-11 sm:grid-cols-2">
                        @foreach ($question->options_json as $key => $text)
                            @php $isCorrect = $key === $question->correct_answer; @endphp
                            <li @class([
                                'flex items-center gap-3 rounded-xl border px-3 py-2 text-sm',
                                'border-gold-400 bg-gold-50 text-ink-900' => $isCorrect,
                                'border-ivory-300 text-slate-600' => ! $isCorrect,
                            ])>
                                <span @class(['flex size-6 shrink-0 items-center justify-center rounded-md text-xs font-semibold', 'bg-gold-500 text-ink-900' => $isCorrect, 'bg-ivory-200 text-slate-500' => ! $isCorrect])>{{ $key }}</span>
                                <span class="min-w-0 flex-1">{{ $text }}</span>
                                @if ($isCorrect) <x-icon name="check-circle" class="size-4 shrink-0 text-gold-600" /> @endif
                            </li>
                        @endforeach
                    </ul>
                </article>
            @empty
                <div class="card">
                    <x-empty-state icon="clipboard" title="No questions yet" message="Add your first multiple-choice question below." />
                </div>
            @endforelse

            @unless ($locked)
                <form method="POST" action="{{ route('teacher.quizzes.questions.store', $quiz) }}" class="card-gold space-y-5 p-6 sm:p-8">
                    @csrf
                    <div>
                        <h2 class="font-serif text-lg font-semibold">Add a question</h2>
                        <p class="text-sm text-slate-500">Options A and B are required; C and D are optional.</p>
                    </div>
                    @include('teacher.quizzes._options', ['question' => null, 'bag' => 'question', 'prefix' => 'new_'])
                    <div class="flex justify-end">
                        <button type="submit" class="btn-gold"><x-icon name="plus" class="size-4" /> Add question</button>
                    </div>
                </form>
            @endunless
        </div>

        {{-- Settings --}}
        <form method="POST" action="{{ route('teacher.quizzes.update', $quiz) }}" class="card h-fit space-y-5 p-6">
            @csrf
            @method('PUT')
            <div>
                <h2 class="font-serif text-lg font-semibold">Quiz settings</h2>
                <p class="text-sm text-slate-500">{{ $quiz->subject->name }} · {{ $quiz->subject->gradeLevel->name }}</p>
            </div>

            <x-field label="Title" name="title">
                <input id="title" name="title" type="text" value="{{ old('title', $quiz->title) }}" required class="input">
            </x-field>
            <x-field label="Passing score (%)" name="passing_score">
                <input id="passing_score" name="passing_score" type="number" min="1" max="100" value="{{ old('passing_score', $quiz->passing_score) }}" required class="input">
            </x-field>
            <x-field label="Instructions" name="description">
                <textarea id="description" name="description" rows="3" class="input">{{ old('description', $quiz->description) }}</textarea>
            </x-field>

            <div>
                <p class="mb-2 text-sm font-medium text-ink-800">Publish to sections</p>
                @forelse ($availableSections as $section)
                    <label class="mb-2 flex cursor-pointer items-center gap-3 rounded-xl border border-ivory-300 px-3 py-2.5 text-sm hover:border-gold-300">
                        <input type="checkbox" name="sections[]" value="{{ $section->id }}" class="rounded border-ivory-300 text-gold-600 focus:ring-gold-500/40"
                               @checked(in_array($section->id, old('sections', $quiz->sections->pluck('id')->all())))>
                        <span class="text-ink-800">{{ $section->name }}</span>
                    </label>
                @empty
                    <p class="text-sm text-amber-700">You are no longer assigned to any section for this subject.</p>
                @endforelse
                @error('sections') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @error('sections.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-3 text-sm text-slate-600">
                <input type="hidden" name="reveal_answers" value="0">
                <input type="checkbox" name="reveal_answers" value="1" class="mt-0.5 rounded border-ivory-300 text-gold-600 focus:ring-gold-500/40"
                       @checked(old('reveal_answers', $quiz->reveal_answers))>
                <span>Show correct answers to students after they submit</span>
            </label>

            <button type="submit" class="btn-dark w-full">Save settings</button>
        </form>
    </div>
</x-layouts.app>
