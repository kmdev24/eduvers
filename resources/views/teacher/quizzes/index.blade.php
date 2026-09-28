<x-layouts.app title="Quizzes">
    <x-page-header eyebrow="Teaching" title="Quizzes"
                   description="Build multiple-choice quizzes and publish them to your sections. Scoring is automatic.">
        <x-slot:actions>
            @if ($subjects->isNotEmpty())
                <a href="{{ route('teacher.quizzes.create', array_filter(['subject_id' => $subjectId])) }}" class="btn-gold">
                    <x-icon name="plus" class="size-4" /> New quiz
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($subjects->isEmpty())
        <div class="card mt-8">
            <x-empty-state icon="stack" title="No subjects assigned yet" message="Quizzes are created under your assigned subjects." />
        </div>
    @else
        <div class="mt-8 flex flex-wrap gap-2">
            <a href="{{ route('teacher.quizzes.index') }}"
               @class(['rounded-full px-4 py-2 text-sm font-medium transition', 'bg-ink-900 text-ivory' => ! $subjectId, 'bg-white text-slate-600 ring-1 ring-ivory-300 hover:ring-gold-300' => $subjectId])>All subjects</a>
            @foreach ($subjects as $subject)
                <a href="{{ route('teacher.quizzes.index', ['subject_id' => $subject->id]) }}"
                   @class(['rounded-full px-4 py-2 text-sm font-medium transition', 'bg-ink-900 text-ivory' => $subjectId === (int) $subject->id, 'bg-white text-slate-600 ring-1 ring-ivory-300 hover:ring-gold-300' => $subjectId !== (int) $subject->id])>{{ $subject->code }}</a>
            @endforeach
        </div>

        <div class="card mt-6">
            @if ($quizzes->isEmpty())
                <x-empty-state icon="clipboard" title="No quizzes yet" message="Create a quiz, add questions, then publish it to your sections." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="px-6 py-3">Quiz</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-center">Questions</th>
                                <th class="px-6 py-3">Sections</th>
                                <th class="px-6 py-3 text-center">Submissions</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ivory-200">
                            @foreach ($quizzes as $quiz)
                                <tr class="transition hover:bg-ivory-50">
                                    <td class="px-6 py-4">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-champagne-dark">{{ $quiz->subject->code }}</p>
                                        <a href="{{ route('teacher.quizzes.edit', $quiz) }}" class="font-medium text-ink-900 hover:text-gold-700">{{ $quiz->title }}</a>
                                        <p class="text-xs text-slate-400">Pass mark {{ $quiz->passing_score }}%</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if ($quiz->is_published)
                                            <span class="badge-gold">Published</span>
                                        @else
                                            <span class="badge-slate">Draft</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center tabular-nums text-slate-600">{{ $quiz->questions_count }}</td>
                                    <td class="px-6 py-4 text-slate-600">
                                        {{ $quiz->sections->pluck('name')->join(', ') ?: '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-center tabular-nums text-slate-600">{{ $quiz->submissions_count }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ route('teacher.quizzes.edit', $quiz) }}" class="icon-btn" title="Open builder" aria-label="Edit {{ $quiz->title }}"><x-icon name="pencil" class="size-5" /></a>
                                            <form method="POST" action="{{ route('teacher.quizzes.destroy', $quiz) }}" data-confirm="Delete &quot;{{ $quiz->title }}&quot; and all its questions?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="icon-btn-danger" aria-label="Delete {{ $quiz->title }}"
                                                        title="{{ $quiz->submissions_count > 0 ? 'Students have taken this quiz' : 'Delete' }}"
                                                        @disabled($quiz->submissions_count > 0)>
                                                    <x-icon name="trash" class="size-5" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-ivory-300 px-6 py-4">{{ $quizzes->links('partials.pagination') }}</div>
            @endif
        </div>
    @endif
</x-layouts.app>
