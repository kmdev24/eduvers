<x-layouts.app title="My Subjects">
    <x-page-header eyebrow="Learning" title="My subjects"
                   :description="$student->section ? $student->section->name.' · '.$student->section->gradeLevel->name.' · '.$student->section->trackStrand->name : 'You are not enrolled in a section yet.'" />

    @if ($classes->isEmpty())
        <div class="card mt-8">
            <x-empty-state icon="stack" title="No subjects yet"
                           :message="$student->section ? 'Your section\'s subjects will appear once teachers are assigned.' : 'Please contact the Registrar\'s Office to be enrolled in a section.'" />
        </div>
    @else
        <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($classes as $offering)
                <a href="{{ route('student.subjects.show', $offering->subject) }}"
                   class="card group relative flex flex-col overflow-hidden p-6 transition duration-300 hover:-translate-y-0.5 hover:shadow-elevated">
                    <div class="absolute inset-x-0 top-0 h-1 bg-gold-gradient opacity-60 transition group-hover:opacity-100"></div>
                    <p class="text-xs font-semibold uppercase tracking-luxe text-champagne-dark">{{ $offering->subject->code }}</p>
                    <h2 class="mt-2 font-serif text-xl font-semibold leading-snug text-ink-900 group-hover:text-gold-800">{{ $offering->subject->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $offering->teacher->name }}</p>
                    <div class="gold-divider my-5"></div>
                    <div class="mt-auto flex items-center gap-5 text-sm text-slate-600">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="book-open" class="size-4 text-gold-600" /> {{ (int) ($lessonCounts[$offering->subject_id] ?? 0) }} lessons</span>
                        <span class="inline-flex items-center gap-1.5"><x-icon name="clipboard" class="size-4 text-gold-600" /> {{ (int) ($quizCounts[$offering->subject_id] ?? 0) }} quizzes</span>
                        <x-icon name="arrow-right" class="ml-auto size-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-gold-600" />
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
