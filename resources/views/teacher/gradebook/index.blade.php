<x-layouts.app title="Gradebook">
    <x-page-header eyebrow="Teaching" title="Gradebook"
                   description="Quiz results for each of your classes, by term." />

    {{-- Filters --}}
    <form method="GET" action="{{ route('teacher.gradebook') }}" class="card mt-8 flex flex-col gap-3 p-5 md:flex-row md:items-end">
        <x-field label="Term" name="term" class="md:w-64">
            <select id="term" name="term" class="input" onchange="this.form.class && (this.form.class.value = ''); this.form.submit()">
                @foreach ($terms as $term)
                    <option value="{{ $term->id }}" @selected($termId === (int) $term->id)>
                        {{ $term->name }}{{ $term->academic_year ? ' · '.$term->academic_year : '' }}{{ $term->is_current ? ' (current)' : '' }}
                    </option>
                @endforeach
            </select>
        </x-field>
        <x-field label="Class" name="class" class="flex-1">
            <select id="class" name="class" class="input" onchange="this.form.submit()" @disabled($classes->isEmpty())>
                @forelse ($classes as $class)
                    <option value="{{ $class->id }}" @selected($selected && (int) $selected->id === (int) $class->id)>
                        {{ $class->subject->code }} · {{ $class->subject->name }} — {{ $class->section->name }}
                    </option>
                @empty
                    <option>No classes in this term</option>
                @endforelse
            </select>
        </x-field>
        <noscript><button type="submit" class="btn-dark">Show</button></noscript>
    </form>

    @if (! $selected)
        <div class="card mt-6">
            <x-empty-state icon="chart" title="No classes for this term" message="Pick another term, or ask the administrator to assign you to a subject in this term." />
        </div>
    @else
        {{-- Summary --}}
        <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Students" :value="$stats['students']" icon="users" :hint="$selected->section->name.' · '.$selected->section->gradeLevel->name" />
            <x-stat-card label="Class average" :value="$stats['average'] !== null ? $stats['average'].'%' : '—'" icon="chart" hint="Across all submitted quizzes" />
            <x-stat-card label="Pass rate" :value="$stats['pass_rate'] !== null ? $stats['pass_rate'].'%' : '—'" icon="check-circle" hint="Submissions at or above pass mark" />
            <x-stat-card label="Completion" :value="$stats['completion'] !== null ? $stats['completion'].'%' : '—'" icon="clipboard" :hint="$stats['quizzes'].' quiz(zes) published to this section'" />
        </div>

        {{-- Matrix --}}
        <div class="card mt-6 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ivory-300 px-6 py-5">
                <div>
                    <h2 class="font-serif text-lg font-semibold">{{ $selected->subject->name }} — {{ $selected->section->name }}</h2>
                    <p class="text-sm text-slate-500">Scores in %. Gold = passed, red = below pass mark, — = not yet taken.</p>
                </div>
                @php $q = ['term' => $termId, 'class' => $selected->id]; @endphp
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('teacher.gradebook.export', $q + ['type' => 'grades']) }}" class="btn-ghost px-3 py-2 text-xs" title="One row per student (opens in Excel)">
                        <x-icon name="download" class="size-4" /> Grades CSV
                    </a>
                    <a href="{{ route('teacher.gradebook.export', $q + ['type' => 'submissions']) }}" class="btn-ghost px-3 py-2 text-xs" title="One row per student per quiz">
                        <x-icon name="download" class="size-4" /> Submissions CSV
                    </a>
                    <a href="{{ route('teacher.gradebook.print', $q) }}" target="_blank" class="btn-ghost px-3 py-2 text-xs" title="Printable report — use Save as PDF">
                        <x-icon name="printer" class="size-4" /> Print / PDF
                    </a>
                    <a href="{{ route('sections.masterlist', $selected->section) }}" target="_blank" class="btn-ghost px-3 py-2 text-xs">
                        <x-icon name="queue-list" class="size-4" /> Master list
                    </a>
                    <a href="{{ route('teacher.quizzes.create', ['subject_id' => $selected->subject_id]) }}" class="btn-outline-gold px-3 py-2 text-xs">
                        <x-icon name="plus" class="size-4" /> New quiz
                    </a>
                </div>
            </div>

            @if ($students->isEmpty())
                <x-empty-state icon="users" title="No students in this section yet" />
            @elseif ($quizzes->isEmpty())
                <x-empty-state icon="clipboard" title="No quizzes published to this section" message="Create a quiz for this subject and publish it to {{ $selected->section->name }}." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="sticky left-0 z-10 bg-white px-6 py-3">Student</th>
                                @foreach ($quizzes as $quiz)
                                    <th class="min-w-28 px-4 py-3 text-center">
                                        <a href="{{ route('teacher.quizzes.edit', $quiz) }}" class="block truncate normal-case tracking-normal text-ink-800 hover:text-gold-700" title="{{ $quiz->title }}">{{ \Illuminate\Support\Str::limit($quiz->title, 18) }}</a>
                                        <span class="font-normal normal-case tracking-normal text-slate-400">pass {{ $quiz->passing_score }}%</span>
                                    </th>
                                @endforeach
                                <th class="px-6 py-3 text-center">Average</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ivory-200">
                            @foreach ($students as $student)
                                @php
                                    $row = $grid->get($student->id, collect());
                                    $avg = $row->isNotEmpty() ? (int) round($row->avg(fn ($s) => $s->percent())) : null;
                                @endphp
                                <tr class="transition hover:bg-ivory-50">
                                    <td class="sticky left-0 z-10 bg-white px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ivory-200 text-xs font-semibold text-ink-800">{{ $student->initials() }}</span>
                                            <span class="font-medium whitespace-nowrap text-ink-900">{{ $student->name }}</span>
                                        </div>
                                    </td>
                                    @foreach ($quizzes as $quiz)
                                        @php $sub = $row->get($quiz->id); @endphp
                                        <td class="px-4 py-3 text-center">
                                            @if ($sub)
                                                <span title="{{ (float) $sub->score }}/{{ $sub->total_items }} · {{ $sub->created_at?->format('M j, g:i A') }}"
                                                      @class([
                                                          'inline-flex min-w-12 justify-center rounded-lg px-2 py-1 text-xs font-semibold tabular-nums',
                                                          'bg-gold-100 text-gold-900 ring-1 ring-gold-300' => $sub->passed,
                                                          'bg-red-50 text-red-700 ring-1 ring-red-200' => ! $sub->passed,
                                                      ])>{{ $sub->percent() }}%</span>
                                            @else
                                                <span class="text-slate-300">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-6 py-3 text-center font-semibold tabular-nums text-ink-900">{{ $avg !== null ? $avg.'%' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-ivory-300 bg-ivory-50 text-xs">
                                <td class="sticky left-0 z-10 bg-ivory-50 px-6 py-3 font-semibold uppercase tracking-wider text-slate-500">Quiz average</td>
                                @foreach ($quizzes as $quiz)
                                    @php $col = $grid->map(fn ($r) => $r->get($quiz->id))->filter(); @endphp
                                    <td class="px-4 py-3 text-center tabular-nums text-slate-600">
                                        {{ $col->isNotEmpty() ? (int) round($col->avg(fn ($s) => $s->percent())).'%' : '—' }}
                                        <span class="block text-slate-400">{{ $col->count() }}/{{ $students->count() }}</span>
                                    </td>
                                @endforeach
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-layouts.app>
