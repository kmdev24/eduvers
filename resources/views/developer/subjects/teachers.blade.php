<x-layouts.app title="Assign Teachers">
    <x-page-header eyebrow="Teacher assignment" :title="$subject->name"
                   description="Choose the teacher for each section that takes this subject. Leave a section unassigned to remove its teacher.">
        <x-slot:actions>
            <a href="{{ route('developer.subjects.edit', $subject) }}" class="btn-ghost"><x-icon name="pencil" class="size-4" /> Edit subject</a>
            <a href="{{ route('developer.subjects.index') }}" class="btn-ghost">Back to subjects</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Subject summary --}}
    <div class="relative mt-8 overflow-hidden rounded-2xl bg-ink-gradient p-6 text-ivory shadow-elevated sm:p-8">
        <div class="pointer-events-none absolute -top-20 -right-16 size-64 rounded-full bg-gold-500/20 blur-3xl"></div>
        <dl class="relative grid gap-6 sm:grid-cols-4">
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-luxe text-gold-400">Code</dt>
                <dd class="mt-1 font-serif text-xl">{{ $subject->code }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-luxe text-gold-400">Grade level</dt>
                <dd class="mt-1 font-serif text-xl">{{ $subject->gradeLevel->name }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-luxe text-gold-400">Strand</dt>
                <dd class="mt-1 font-serif text-xl">{{ $subject->trackStrand?->name ?? 'Core (all strands)' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] font-semibold uppercase tracking-luxe text-gold-400">Term</dt>
                <dd class="mt-1 font-serif text-xl">{{ $subject->academicTerm->name }}</dd>
            </div>
        </dl>
    </div>

    @if ($teachers->isEmpty())
        <div class="mt-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <x-icon name="shield" class="mt-0.5 size-4 shrink-0" />
            <p>There are no teacher accounts yet. <a href="{{ route('developer.users.create') }}" class="font-medium underline">Add a teacher</a> first.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('developer.subjects.teachers.update', $subject) }}" class="card mt-6">
        @csrf
        @method('PUT')

        <div class="border-b border-ivory-300 px-6 py-5">
            <h2 class="font-serif text-lg font-semibold">Sections taking this subject</h2>
            <p class="text-sm text-slate-500">
                {{ $sections->count() }} {{ \Illuminate\Support\Str::plural('section', $sections->count()) }} in {{ $subject->gradeLevel->name }}{{ $subject->trackStrand ? ' · '.$subject->trackStrand->name : '' }}
            </p>
        </div>

        @if ($sections->isEmpty())
            <x-empty-state icon="building" title="No matching sections"
                           message="Create a {{ $subject->gradeLevel->name }} section{{ $subject->trackStrand ? ' for '.$subject->trackStrand->name : '' }} first." />
            <div class="pb-8 text-center">
                <a href="{{ route('developer.sections.create') }}" class="btn-outline-gold"><x-icon name="plus" class="size-4" /> New section</a>
            </div>
        @else
            @php $teacherGroups = $teachers->groupBy(fn ($t) => $t->teacher_type?->label() ?? 'Type not set'); @endphp
            <ul class="divide-y divide-ivory-200">
                @foreach ($sections as $section)
                    @php $current = old("teachers.{$section->id}", $assigned[$section->id] ?? ''); @endphp
                    <li class="grid items-center gap-4 px-6 py-4 md:grid-cols-[1fr_20rem]">
                        <div class="flex items-center gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl {{ $current ? 'bg-gold-50 text-gold-600 ring-1 ring-gold-200' : 'bg-ivory-200 text-slate-400 ring-1 ring-ivory-300' }}">
                                <x-icon name="building" class="size-5" />
                            </span>
                            <div>
                                <p class="font-medium text-ink-900">{{ $section->name }}</p>
                                <p class="text-sm text-slate-500">{{ $section->trackStrand->name }} · {{ $section->students_count }}/{{ $section->capacity }} students</p>
                            </div>
                        </div>
                        <div>
                            <label for="teacher_{{ $section->id }}" class="sr-only">Teacher for {{ $section->name }}</label>
                            <select id="teacher_{{ $section->id }}" name="teachers[{{ $section->id }}]" class="input">
                                <option value="">— Unassigned —</option>
                                @foreach ($teacherGroups as $type => $group)
                                    <optgroup label="{{ $type }}">
                                        @foreach ($group as $teacher)
                                            <option value="{{ $teacher->id }}" @selected((string) $current === (string) $teacher->id)>{{ $teacher->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error("teachers.{$section->id}") <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="flex flex-col-reverse items-center justify-end gap-3 border-t border-ivory-300 bg-ivory-50/60 px-6 py-4 sm:flex-row">
                <a href="{{ route('developer.subjects.index') }}" class="text-sm text-slate-500 hover:text-ink-900">Cancel</a>
                <button type="submit" class="btn-gold">Save assignments</button>
            </div>
        @endif
    </form>
</x-layouts.app>
