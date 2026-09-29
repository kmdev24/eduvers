<x-layouts.app :title="$lesson->title">
    <x-page-header :eyebrow="$lesson->subject->code.' · '.$lesson->subject->name" :title="$lesson->title"
                   :description="'Last updated '.$lesson->updated_at?->format('M j, Y g:i A')">
        <x-slot:actions>
            <a href="{{ route('teacher.lessons.index') }}" class="btn-ghost">All lessons</a>
            @can('update', $lesson)
                <a href="{{ route('teacher.lessons.edit', $lesson) }}" class="btn-dark"><x-icon name="pencil" class="size-4" /> Edit</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($lesson->hasVideo())
        <div class="mt-8">
            @include('partials.lesson-video', ['lesson' => $lesson])
        </div>
    @endif

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        <article class="card p-6 sm:p-10 xl:col-span-2">
            @if (filled($lesson->content))
                <div class="prose-eduvers">{{ $lesson->renderedContent() }}</div>
            @else
                <x-empty-state icon="book-open" title="No written content" message="This lesson has no written content." />
            @endif
        </article>

        <aside class="space-y-6">
            @include('partials.lesson-attachment', ['lesson' => $lesson])
            <div class="card p-6 text-sm text-slate-500">
                <p class="font-medium text-ink-900">Student preview</p>
                <p class="mt-1">This is how students see the lesson content. The video appears first, then the content, and they can open PDFs in the browser or download the file.</p>
            </div>
        </aside>
    </div>
</x-layouts.app>
