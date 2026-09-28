@use('App\Support\Gradebook')
@php $class = $selected; @endphp

<x-layouts.print :title="'Class Record — '.$class->subject->name" orientation="landscape"
                 :subtitle="$class->subject->code.' · '.$class->section->name.' · '.$class->section->gradeLevel->name.' · '.$class->section->trackStrand->name.($term ? ' · '.$term->name.($term->academic_year ? ' '.$term->academic_year : '') : '')">
    <x-slot:actions>
        <a href="{{ route('teacher.gradebook.export', ['term' => $termId, 'class' => $class->id, 'type' => 'grades']) }}" class="rounded-lg border border-white/20 px-3 py-2 text-xs hover:border-gold-400">Download CSV</a>
    </x-slot:actions>

    {{-- Summary --}}
    <dl class="mb-6 grid grid-cols-5 gap-3 text-center text-sm">
        @foreach ([
            'Teacher'      => $teacher->name,
            'Students'     => $stats['students'],
            'Class average'=> $stats['average'] !== null ? $stats['average'].'%' : '—',
            'Pass rate'    => $stats['pass_rate'] !== null ? $stats['pass_rate'].'%' : '—',
            'Completion'   => $stats['completion'] !== null ? $stats['completion'].'%' : '—',
        ] as $label => $value)
            <div class="rounded-lg border border-ivory-300 px-3 py-2">
                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</dt>
                <dd class="mt-0.5 font-semibold text-ink-900">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($students->isEmpty() || $quizzes->isEmpty())
        <p class="rounded-lg border border-ivory-300 p-6 text-center text-sm text-slate-500">
            {{ $students->isEmpty() ? 'No students in this section.' : 'No quizzes published to this section yet.' }}
        </p>
    @else
        <table class="w-full border-collapse text-xs">
            <thead>
                <tr class="bg-ink-900 text-ivory">
                    <th class="border border-ink-800 px-2 py-2 text-left">No.</th>
                    <th class="border border-ink-800 px-2 py-2 text-left">Student</th>
                    @foreach ($quizzes as $i => $quiz)
                        <th class="border border-ink-800 px-2 py-2 text-center" title="{{ $quiz->title }}">
                            Q{{ $i + 1 }}<span class="block font-normal text-gold-300">{{ $quiz->passing_score }}%</span>
                        </th>
                    @endforeach
                    <th class="border border-ink-800 px-2 py-2 text-center">Average</th>
                    <th class="border border-ink-800 px-2 py-2 text-center">Passed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($students->values() as $n => $student)
                    @php $row = $grid->get($student->id, collect()); $avg = Gradebook::studentAverage($row); @endphp
                    <tr class="even:bg-ivory-50">
                        <td class="border border-ivory-300 px-2 py-1.5">{{ $n + 1 }}</td>
                        <td class="border border-ivory-300 px-2 py-1.5 font-medium whitespace-nowrap">{{ $student->name }}</td>
                        @foreach ($quizzes as $quiz)
                            @php $sub = $row->get($quiz->id); @endphp
                            <td @class(['border border-ivory-300 px-2 py-1.5 text-center tabular-nums', 'text-red-700' => $sub && ! $sub->passed, 'font-semibold' => $sub && $sub->passed])>
                                {{ $sub ? $sub->percent() : '—' }}
                            </td>
                        @endforeach
                        <td class="border border-ivory-300 px-2 py-1.5 text-center font-semibold tabular-nums">{{ $avg !== null ? $avg.'%' : '—' }}</td>
                        <td class="border border-ivory-300 px-2 py-1.5 text-center tabular-nums">{{ $row->where('passed', true)->count() }}/{{ $quizzes->count() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Quiz legend --}}
        <div class="mt-6">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Quiz key</p>
            <ol class="mt-2 grid grid-cols-2 gap-x-8 gap-y-1 text-xs text-slate-700">
                @foreach ($quizzes as $i => $quiz)
                    <li><span class="font-semibold">Q{{ $i + 1 }}</span> — {{ $quiz->title }} ({{ $quiz->questions_count }} items, pass {{ $quiz->passing_score }}%)</li>
                @endforeach
            </ol>
            <p class="mt-3 text-xs text-slate-500">Scores are percentages. Red = below pass mark. — = not taken.</p>
        </div>
    @endif
</x-layouts.print>
