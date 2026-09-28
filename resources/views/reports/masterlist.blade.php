<x-layouts.print :title="'Student Master List — '.$section->name"
                 :subtitle="$section->gradeLevel->name.' · '.$section->gradeLevel->level_type->label().' · '.$section->trackStrand->name.' ('.$section->trackStrand->category->label().')'">
    <x-slot:actions>
        <a href="{{ route('sections.masterlist', ['section' => $section, 'format' => 'csv']) }}" class="rounded-lg border border-white/20 px-3 py-2 text-xs hover:border-gold-400">Download CSV</a>
    </x-slot:actions>

    <dl class="mb-6 grid grid-cols-3 gap-3 text-center text-sm">
        <div class="rounded-lg border border-ivory-300 px-3 py-2">
            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Enrolled</dt>
            <dd class="mt-0.5 font-semibold">{{ $students->count() }} / {{ $section->capacity }}</dd>
        </div>
        <div class="rounded-lg border border-ivory-300 px-3 py-2">
            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Subjects</dt>
            <dd class="mt-0.5 font-semibold">{{ $teachers->count() }}</dd>
        </div>
        <div class="rounded-lg border border-ivory-300 px-3 py-2">
            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Date</dt>
            <dd class="mt-0.5 font-semibold">{{ now()->format('M j, Y') }}</dd>
        </div>
    </dl>

    <table class="w-full border-collapse text-sm">
        <thead>
            <tr class="bg-ink-900 text-ivory">
                <th class="w-12 border border-ink-800 px-3 py-2 text-left">No.</th>
                <th class="border border-ink-800 px-3 py-2 text-left">Student name</th>
                <th class="border border-ink-800 px-3 py-2 text-left">Email</th>
                <th class="w-40 border border-ink-800 px-3 py-2 text-left">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students->values() as $n => $student)
                <tr class="even:bg-ivory-50">
                    <td class="border border-ivory-300 px-3 py-2 tabular-nums">{{ $n + 1 }}</td>
                    <td class="border border-ivory-300 px-3 py-2 font-medium">{{ $student->name }}</td>
                    <td class="border border-ivory-300 px-3 py-2 text-slate-600">{{ $student->email }}</td>
                    <td class="border border-ivory-300 px-3 py-2"></td>
                </tr>
            @empty
                <tr><td colspan="4" class="border border-ivory-300 px-3 py-6 text-center text-slate-500">No students enrolled in this section.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($teachers->isNotEmpty())
        <div class="mt-8">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Subjects &amp; teachers</p>
            <table class="mt-2 w-full border-collapse text-xs">
                <tbody>
                    @foreach ($teachers as $offering)
                        <tr>
                            <td class="w-32 border border-ivory-300 px-3 py-1.5 font-semibold">{{ $offering->subject->code }}</td>
                            <td class="border border-ivory-300 px-3 py-1.5">{{ $offering->subject->name }}</td>
                            <td class="border border-ivory-300 px-3 py-1.5">{{ $offering->teacher->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.print>
