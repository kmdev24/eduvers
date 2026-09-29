<x-layouts.app title="Lessons">
    <x-page-header eyebrow="Teaching" title="Lessons"
                   description="Write lessons for your subjects and attach handouts, slides or PDFs.">
        <x-slot:actions>
            @if ($subjects->isNotEmpty())
                <a href="{{ route('teacher.lessons.create', array_filter(['subject_id' => $subjectId])) }}" class="btn-gold">
                    <x-icon name="plus" class="size-4" /> New lesson
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($subjects->isEmpty())
        <div class="card mt-8">
            <x-empty-state icon="stack" title="No subjects assigned yet" message="Lessons are written under your assigned subjects. Ask the administrator to assign you to a subject and section." />
        </div>
    @else
        {{-- Subject filter chips --}}
        <div class="mt-8 flex flex-wrap gap-2">
            <a href="{{ route('teacher.lessons.index') }}"
               @class(['rounded-full px-4 py-2 text-sm font-medium transition', 'bg-ink-900 text-ivory' => ! $subjectId, 'bg-white text-slate-600 ring-1 ring-ivory-300 hover:ring-gold-300' => $subjectId])>All subjects</a>
            @foreach ($subjects as $subject)
                <a href="{{ route('teacher.lessons.index', ['subject_id' => $subject->id]) }}"
                   @class(['rounded-full px-4 py-2 text-sm font-medium transition', 'bg-ink-900 text-ivory' => $subjectId === (int) $subject->id, 'bg-white text-slate-600 ring-1 ring-ivory-300 hover:ring-gold-300' => $subjectId !== (int) $subject->id])>{{ $subject->code }}</a>
            @endforeach
        </div>

        @if ($lessons->isEmpty())
            <div class="card mt-6">
                <x-empty-state icon="book-open" title="No lessons yet" message="Create your first lesson to share it with your students." />
            </div>
        @else
            <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($lessons as $lesson)
                    <article class="card group flex flex-col p-6 transition duration-300 hover:-translate-y-0.5 hover:shadow-elevated">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-wider text-champagne-dark">{{ $lesson->subject->code }}</span>
                            <span class="flex flex-wrap justify-end gap-1.5">
                            @if ($lesson->hasVideo())
                                <span class="badge-slate"><x-icon name="play" class="mr-1 size-3" /> Video</span>
                            @endif
                            @if ($lesson->hasAttachment())
                                <span class="badge-gold" title="{{ $lesson->original_filename }}">
                                    {{ $lesson->isPdf() ? 'PDF' : 'File' }} attached
                                </span>
                            @endif
                            </span>
                        </div>
                        <a href="{{ route('teacher.lessons.show', $lesson) }}" class="mt-2 font-serif text-xl font-semibold leading-snug text-ink-900 hover:text-gold-700">{{ $lesson->title }}</a>
                        <p class="mt-2 flex-1 text-sm text-slate-500">{{ $lesson->excerpt() ?: ($lesson->hasVideo() ? 'Video lesson.' : 'No written content.') }}</p>
                        <div class="gold-divider my-4"></div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400">Updated {{ $lesson->updated_at?->diffForHumans() }}</span>
                            <div class="flex items-center gap-1">
                                <a href="{{ route('teacher.lessons.edit', $lesson) }}" class="icon-btn" title="Edit" aria-label="Edit {{ $lesson->title }}"><x-icon name="pencil" class="size-4" /></a>
                                <form method="POST" action="{{ route('teacher.lessons.destroy', $lesson) }}" data-confirm="Delete &quot;{{ $lesson->title }}&quot;? Its attachment will also be deleted.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn-danger" title="Delete" aria-label="Delete {{ $lesson->title }}"><x-icon name="trash" class="size-4" /></button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-6">{{ $lessons->links('partials.pagination') }}</div>
        @endif
    @endif
</x-layouts.app>
