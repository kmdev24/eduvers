@use('App\Enums\TrackCategory')

<x-layouts.app title="Sections">
    <x-page-header eyebrow="Academic structure" title="Sections"
                   description="SHS (Grade 11–12) and College sections, their strands and capacity.">
        <x-slot:actions>
            <a href="{{ route('developer.sections.create') }}" class="btn-gold">
                <x-icon name="plus" class="size-4" /> New section
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Summary --}}
    <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Sections" :value="$summary['sections']" icon="building" />
        <x-stat-card label="Total seats" :value="number_format($summary['capacity'])" icon="users" />
        <x-stat-card label="Enrolled" :value="number_format($summary['enrolled'])" icon="academic-cap" />
        <x-stat-card label="Fill rate" :value="$summary['fill'].'%'" icon="chart" />
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-3">

        {{-- Sections table --}}
        <div class="card xl:col-span-2">
            <form method="GET" action="{{ route('developer.sections.index') }}" class="flex flex-col gap-3 border-b border-ivory-300 p-5 sm:flex-row sm:items-center">
                <select name="grade_level_id" class="input sm:w-48" aria-label="Grade level">
                    <option value="">All levels</option>
                    @foreach ($gradeLevels as $level)
                        <option value="{{ $level->id }}" @selected($filters['grade_level_id'] === $level->id)>{{ $level->name }}</option>
                    @endforeach
                </select>
                <select name="track_strand_id" class="input sm:w-56" aria-label="Track or strand">
                    <option value="">All tracks &amp; strands</option>
                    @foreach ($strands->groupBy(fn ($s) => $s->category->label()) as $category => $group)
                        <optgroup label="{{ $category }}">
                            @foreach ($group as $strand)
                                <option value="{{ $strand->id }}" @selected($filters['track_strand_id'] === $strand->id)>{{ $strand->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="btn-dark">Filter</button>
                    @if ($filters['grade_level_id'] || $filters['track_strand_id'])
                        <a href="{{ route('developer.sections.index') }}" class="btn-ghost">Reset</a>
                    @endif
                </div>
            </form>

            @if ($sections->isEmpty())
                <x-empty-state icon="building" title="No sections found" message="Create a section, or change the filters." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="px-6 py-3">Section</th>
                                <th class="px-6 py-3">Track / strand</th>
                                <th class="px-6 py-3">Enrolled</th>
                                <th class="px-6 py-3 text-center">Classes</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ivory-200">
                            @foreach ($sections as $section)
                                @php $fill = $section->capacity > 0 ? min(100, round($section->students_count / $section->capacity * 100)) : 0; @endphp
                                <tr class="transition hover:bg-ivory-50">
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-ink-900">{{ $section->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $section->gradeLevel->name }} · {{ $section->gradeLevel->level_type->label() }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="{{ $section->trackStrand->category === TrackCategory::College ? 'badge-gold' : 'badge-slate' }}">{{ $section->trackStrand->name }}</span>
                                        <span class="ml-1 text-xs text-slate-400">{{ $section->trackStrand->category->label() }}</span>
                                    </td>
                                    <td class="min-w-48 px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ivory-200">
                                                <div class="h-full rounded-full {{ $fill >= 100 ? 'bg-red-400' : 'bg-gold-gradient' }}" style="width: {{ $fill }}%"></div>
                                            </div>
                                            <span class="w-14 text-right text-xs tabular-nums text-slate-500">{{ $section->students_count }}/{{ $section->capacity }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center tabular-nums text-slate-600">{{ $section->assignments_count }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ route('sections.masterlist', $section) }}" target="_blank" class="icon-btn" title="Master list (print)" aria-label="Master list for {{ $section->name }}">
                                                <x-icon name="queue-list" class="size-5" />
                                            </a>
                                            <a href="{{ route('developer.sections.edit', $section) }}" class="icon-btn" title="Edit" aria-label="Edit {{ $section->name }}">
                                                <x-icon name="pencil" class="size-5" />
                                            </a>
                                            <form method="POST" action="{{ route('developer.sections.destroy', $section) }}"
                                                  data-confirm="Delete section {{ $section->name }}? Its teacher assignments will also be removed.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="icon-btn-danger" aria-label="Delete {{ $section->name }}"
                                                        title="{{ $section->students_count > 0 ? 'Move students out before deleting' : 'Delete' }}"
                                                        @disabled($section->students_count > 0)>
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
                    {{ $sections->links('partials.pagination') }}
                </div>
            @endif
        </div>

        {{-- Tracks & strands (managed on their own page) --}}
        <div class="card h-fit">
            <div class="border-b border-ivory-300 px-6 py-5">
                <h2 class="font-serif text-lg font-semibold">Tracks &amp; strands</h2>
                <p class="text-sm text-slate-500">Programs sections can be assigned to</p>
            </div>
            <ul class="divide-y divide-ivory-200">
                @forelse ($strands as $strand)
                    <li class="flex items-center justify-between gap-3 px-6 py-3">
                        <div class="min-w-0">
                            <p class="font-medium text-ink-900">{{ $strand->name }}</p>
                            <p class="text-xs text-slate-500">{{ $strand->category->label() }}</p>
                        </div>
                        <span class="text-xs tabular-nums text-slate-500">{{ $strand->sections_count }} {{ \Illuminate\Support\Str::plural('section', $strand->sections_count) }}</span>
                    </li>
                @empty
                    <li><x-empty-state icon="academic-cap" title="No strands yet" /></li>
                @endforelse
            </ul>
            <div class="border-t border-ivory-300 p-5">
                <a href="{{ route('developer.tracks.index') }}" class="btn-outline-gold w-full">Manage tracks &amp; strands</a>
            </div>
        </div>
    </div>
</x-layouts.app>
