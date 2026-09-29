<x-layouts.app title="Subjects">
    <x-page-header eyebrow="Curriculum" title="Subjects & teachers"
                   description="Subjects per grade level and term, and which teacher handles each section.">
        <x-slot:actions>
            <a href="{{ route('developer.subjects.create') }}" class="btn-gold">
                <x-icon name="plus" class="size-4" /> New subject
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card mt-8">
        {{-- Filters --}}
        <form method="GET" action="{{ route('developer.subjects.index') }}" class="grid gap-3 border-b border-ivory-300 p-5 md:grid-cols-2 xl:grid-cols-[1fr_auto_auto_auto_auto]">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search code or name" class="input pl-10">
            </div>
            <select name="grade_level_id" class="input xl:w-40" aria-label="Grade level">
                <option value="">All levels</option>
                @foreach ($gradeLevels as $level)
                    <option value="{{ $level->id }}" @selected($filters['grade_level_id'] === $level->id)>{{ $level->name }}</option>
                @endforeach
            </select>
            <select name="academic_term_id" class="input xl:w-48" aria-label="Term">
                <option value="">All terms</option>
                @foreach ($terms as $term)
                    <option value="{{ $term->id }}" @selected($filters['academic_term_id'] === $term->id)>{{ $term->name }}{{ $term->academic_year ? ' · '.$term->academic_year : '' }}</option>
                @endforeach
            </select>
            <select name="strand" class="input xl:w-44" aria-label="Strand">
                <option value="">All strands</option>
                <option value="core" @selected($filters['strand'] === 'core')>Core subjects</option>
                @foreach ($strands as $strand)
                    <option value="{{ $strand->id }}" @selected($filters['strand'] === (string) $strand->id)>{{ $strand->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-dark">Filter</button>
                @if (array_filter($filters))
                    <a href="{{ route('developer.subjects.index') }}" class="btn-ghost">Reset</a>
                @endif
            </div>
        </form>

        @if ($subjects->isEmpty())
            <x-empty-state icon="stack" title="No subjects found" message="Create a subject, or change the filters." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="px-6 py-3">Subject</th>
                            <th class="px-6 py-3">Level</th>
                            <th class="px-6 py-3">Strand</th>
                            <th class="px-6 py-3">Term</th>
                            <th class="px-6 py-3">Teachers</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ivory-200">
                        @foreach ($subjects as $subject)
                            @php
                                $eligible = $eligibleCounts[$subject->id] ?? 0;
                                $covered  = $eligible > 0 && $subject->assignments_count >= $eligible;
                            @endphp
                            <tr class="transition hover:bg-ivory-50">
                                <td class="px-6 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-champagne-dark">{{ $subject->code }}</p>
                                    <p class="font-medium text-ink-900">{{ $subject->name }}</p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-slate-600">{{ $subject->gradeLevel->name }}</td>
                                <td class="px-6 py-4">
                                    @if ($subject->trackStrand)
                                        <span class="badge-slate">{{ $subject->trackStrand->name }}</span>
                                    @else
                                        <span class="badge-gold">Core</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-slate-600">{{ $subject->academicTerm->name }}</td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('developer.subjects.teachers.edit', $subject) }}"
                                       class="inline-flex items-center gap-2 whitespace-nowrap rounded-full px-3 py-1 text-xs font-medium ring-1 transition
                                              {{ $covered ? 'bg-gold-50 text-gold-800 ring-gold-300 hover:bg-gold-100' : 'bg-white text-slate-600 ring-ivory-300 hover:text-ink-900 hover:ring-gold-300' }}">
                                        <span class="size-1.5 rounded-full {{ $covered ? 'bg-gold-500' : ($subject->assignments_count > 0 ? 'bg-amber-400' : 'bg-slate-300') }}"></span>
                                        {{ $subject->assignments_count }} / {{ $eligible }} {{ \Illuminate\Support\Str::plural('section', $eligible) }}
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('developer.subjects.teachers.edit', $subject) }}" class="icon-btn" title="Assign teachers" aria-label="Assign teachers for {{ $subject->code }}">
                                            <x-icon name="users" class="size-5" />
                                        </a>
                                        <a href="{{ route('developer.subjects.edit', $subject) }}" class="icon-btn" title="Edit" aria-label="Edit {{ $subject->code }}">
                                            <x-icon name="pencil" class="size-5" />
                                        </a>
                                        <form method="POST" action="{{ route('developer.subjects.destroy', $subject) }}"
                                              data-confirm="Delete {{ $subject->code }}? Its teacher assignments will also be removed.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-btn-danger" title="Delete" aria-label="Delete {{ $subject->code }}">
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
            <div class="border-t border-ivory-300 px-6 py-4">
                {{ $subjects->links('partials.pagination') }}
            </div>
        @endif
    </div>
</x-layouts.app>
