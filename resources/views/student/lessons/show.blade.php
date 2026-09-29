<x-layouts.app :title="$lesson->title">
    <a href="{{ route('student.subjects.show', $lesson->subject) }}" class="text-sm font-medium text-gold-700 hover:text-gold-900">← {{ $lesson->subject->name }}</a>

    {{-- Lesson header --}}
    <header class="mt-4">
        <p class="eyebrow">{{ $lesson->subject->code }}</p>
        <h1 class="heading-serif mt-2 text-3xl sm:text-4xl">{{ $lesson->title }}</h1>
        <p class="mt-3 text-sm text-slate-500">
            @if ($lesson->teacher) {{ $lesson->teacher->name }} · @endif
            Posted {{ $lesson->created_at?->format('F j, Y') }}
        </p>
    </header>

    {{-- Video first, full width --}}
    @if ($lesson->hasVideo())
        <div class="mt-6">
            @include('partials.lesson-video', ['lesson' => $lesson])
        </div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <article class="card p-6 sm:p-10 xl:col-span-2">
            @if (filled($lesson->content))
                <div class="prose-eduvers">{{ $lesson->renderedContent() }}</div>
            @elseif ($lesson->hasVideo())
                <p class="text-slate-500">Watch the video above. {{ $lesson->hasAttachment() ? 'Materials are available on the right.' : '' }}</p>
            @else
                <p class="text-slate-500">This lesson is provided as an attachment. Use the buttons on the right to open or download it.</p>
            @endif

            @if ($previous || $next)
                <div class="mt-10 grid gap-3 border-t border-ivory-300 pt-6 sm:grid-cols-2">
                    <div>
                        @if ($previous)
                            <a href="{{ route('student.lessons.show', $previous->id) }}" class="group block rounded-xl border border-ivory-300 p-4 transition hover:border-gold-300">
                                <span class="text-xs text-slate-400">← Previous</span>
                                <span class="mt-1 block truncate font-medium text-ink-900 group-hover:text-gold-800">{{ $previous->title }}</span>
                            </a>
                        @endif
                    </div>
                    <div>
                        @if ($next)
                            <a href="{{ route('student.lessons.show', $next->id) }}" class="group block rounded-xl border border-ivory-300 p-4 text-right transition hover:border-gold-300">
                                <span class="text-xs text-slate-400">Next →</span>
                                <span class="mt-1 block truncate font-medium text-ink-900 group-hover:text-gold-800">{{ $next->title }}</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </article>

        <aside class="space-y-6">
            @include('partials.lesson-attachment', ['lesson' => $lesson])
            <a href="{{ route('student.subjects.show', $lesson->subject) }}" class="card flex items-center justify-between p-5 text-sm font-medium text-ink-800 transition hover:border-gold-300">
                All lessons in {{ $lesson->subject->code }}
                <x-icon name="arrow-right" class="size-4 text-gold-600" />
            </a>
        </aside>
    </div>
</x-layouts.app>
