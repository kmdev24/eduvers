@use('App\Enums\LevelType')

<x-layouts.app title="Enroll students">
    <x-page-header eyebrow="Admission" title="Enroll students in a strand or course"
                   description="Choose a Senior High School strand or a College course and put students into one of its sections. They get every subject of that programme straight away." />

    @if ($waitingCount > 0)
        <div class="mt-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <x-icon name="user-plus" class="mt-0.5 size-4 shrink-0" />
            <p>{{ $waitingCount }} {{ \Illuminate\Support\Str::plural('student', $waitingCount) }} not enrolled in any strand or course yet.</p>
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.student-enrollment.index') }}" class="card mt-6 flex flex-col gap-3 p-5 lg:flex-row lg:items-center">
        <select id="level_type" name="level_type" class="input lg:w-56" aria-label="Senior High School or College">
            <option value="">All programmes</option>
            @foreach (LevelType::cases() as $type)
                <option value="{{ $type->value }}" @selected($filters['level_type'] === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>

        {{-- Narrows to the chosen programme's strands as soon as it is picked --}}
        <select id="strand" name="strand" class="input lg:w-56" aria-label="Strand or course"
                data-depends="level_type=levelType">
            <option value="">All strands &amp; courses</option>
            @foreach ($strands as $strand)
                <option value="{{ $strand->id }}" data-level-type="{{ $strand->category->isCollege() ? 'college' : 'shs' }}"
                        @selected($filters['strand'] === $strand->id)>{{ $strand->name }} · {{ $strand->category->label() }}</option>
            @endforeach
        </select>

        <select name="show" class="input lg:w-52" aria-label="Which students">
            <option value="new" @selected($filters['show'] === 'new')>Not enrolled yet</option>
            <option value="all" @selected($filters['show'] === 'all')>All students</option>
        </select>

        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search by name or email" class="input pl-10" aria-label="Search students">
        </div>

        <button type="submit" class="btn-dark">Search</button>
    </form>

    @if ($students->isEmpty())
        <div class="card mt-6">
            <x-empty-state icon="users"
                           :title="$filters['show'] === 'new' ? 'Everyone is enrolled' : 'No students found'"
                           :message="$filters['show'] === 'new' ? 'Every student belongs to a strand or course. Switch to “All students” to move someone.' : 'No student matches that name or email.'" />
        </div>
    @elseif ($sections->isEmpty())
        <div class="card mt-6">
            <x-empty-state icon="building" title="No sections to enroll into"
                           message="Create a section for the strand or course first." />
            <div class="pb-8 text-center">
                <a href="{{ route('admin.sections.create') }}" class="btn-outline-gold"><x-icon name="plus" class="size-4" /> New section</a>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('admin.student-enrollment.store') }}" class="card mt-6">
            @csrf

            {{-- Where they are going --}}
            <div class="flex flex-col gap-3 border-b border-ivory-300 bg-ivory-50/60 px-6 py-5 lg:flex-row lg:items-end">
                <div class="flex-1">
                    <label for="section_id" class="mb-2 block text-sm font-medium text-ink-800">Strand or course section</label>
                    <select id="section_id" name="section_id" required class="input"
                            data-depends="level_type=levelType,strand=strandId">
                        @foreach ($sections as $programme => $group)
                            <optgroup label="{{ $programme }}">
                                @foreach ($group as $section)
                                    <option value="{{ $section->id }}" @disabled($section->isFull())
                                            data-grade-level-id="{{ $section->grade_level_id }}"
                                            data-level-type="{{ $section->gradeLevel->level_type->value }}"
                                            data-strand-id="{{ $section->track_strand_id }}">
                                        {{ $section->trackStrand->name }} · {{ $section->gradeLevel->name }} · {{ $section->name }}
                                        ({{ $section->availableSlots() }} of {{ $section->capacity }} free){{ $section->isFull() ? ' — full' : '' }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('section_id') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-gold"><x-icon name="user-plus" class="size-4" /> Enroll selected students</button>
            </div>

            @error('student_ids') <p class="px-6 pt-4 text-sm text-red-600">{{ $message }}</p> @enderror

            {{-- Who is going --}}
            <ul class="divide-y divide-ivory-200" data-check-all-group data-level-guard="section_id">
                <li class="flex items-center gap-3 bg-ivory-50/40 px-6 py-3">
                    <input type="checkbox" id="check_all" data-check-all class="size-4 rounded border-ivory-300 text-gold-600 focus:ring-gold-500/30">
                    <label for="check_all" class="text-sm font-medium text-slate-500">Select all {{ $students->count() }} on this page</label>
                </li>

                @foreach ($students as $student)
                    <li class="flex items-center gap-3 px-6 py-4 transition"
                        data-grade-level-id="{{ $student->grade_level_id }}"
                        data-strand-id="{{ $student->track_strand_id }}">
                        <input type="checkbox" id="student_{{ $student->id }}" name="student_ids[]" value="{{ $student->id }}"
                               class="size-4 rounded border-ivory-300 text-gold-600 focus:ring-gold-500/30 disabled:cursor-not-allowed">
                        <label for="student_{{ $student->id }}" class="min-w-0 flex-1">
                            <span class="block font-medium text-ink-900">{{ $student->name }}</span>
                            <span class="block text-xs break-all text-slate-500">{{ $student->email }}</span>
                        </label>

                        {{-- Filled in by app.js: which of the two does not line up --}}
                        <span data-level-warning hidden
                              class="shrink-0 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800 ring-1 ring-amber-200"></span>

                        @if ($student->section)
                            <span class="shrink-0 text-xs text-slate-500">
                                {{ $student->section->trackStrand->name }} · {{ $student->section->gradeLevel->name }} · {{ $student->section->name }}
                            </span>
                        @elseif ($student->gradeLevel)
                            {{-- Admitted into a year level, not placed in a section yet --}}
                            <span class="shrink-0 text-xs text-slate-500">
                                {{ $student->trackStrand?->name ?? 'No strand' }} · {{ $student->gradeLevel->name }} · <span class="text-amber-700">no section</span>
                            </span>
                        @else
                            <span class="badge-slate shrink-0">Not enrolled</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="border-t border-ivory-300 px-6 py-4">
                {{ $students->links('partials.pagination') }}
            </div>
        </form>
    @endif
</x-layouts.app>
