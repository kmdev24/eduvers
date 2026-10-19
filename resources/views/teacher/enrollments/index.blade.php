<x-layouts.app title="Add students">
    <x-page-header eyebrow="Teaching" title="Add students to a class"
                   description="Every student in the school is listed. Students already in the class, or who cannot join it, show the reason instead of an Add button. The student can open the class once an admin approves the request." />

    @if ($classes->isEmpty())
        <div class="card mt-8">
            <x-empty-state icon="building" title="No classes yet" message="You can add students once an admin assigns you a class." />
        </div>
    @else
        <div class="card mt-8">
            <form method="GET" action="{{ route('teacher.enrollments.index') }}"
                  data-live-form data-live-target="#class-roster" data-live-url="{{ route('teacher.enrollments.index', ['roster' => 1]) }}"
                  class="flex flex-col gap-3 border-b border-ivory-300 p-5 md:flex-row md:items-center">
                <select name="class" class="input md:w-80" aria-label="Class">
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($selected?->id === $class->id)>{{ $class->subject->code }} · {{ $class->subject->name }} ({{ $class->section->name }})</option>
                    @endforeach
                </select>
                <div class="relative flex-1">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search students by name or email" class="input pl-10" aria-label="Search students">
                </div>
                <button type="submit" class="btn-dark">Search</button>
            </form>

            <div id="class-roster">
                @include('partials.class-roster')
            </div>
        </div>

        @if ($requests->isNotEmpty())
            <div class="card mt-8">
                <div class="border-b border-ivory-300 px-6 py-4">
                    <h2 class="font-serif text-xl font-semibold text-ink-900">Requests for your classes</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="px-6 py-3">Student</th>
                                <th class="px-6 py-3">Class</th>
                                <th class="px-6 py-3">Sent</th>
                                <th class="px-6 py-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ivory-200">
                            @foreach ($requests as $request)
                                <tr>
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-ink-900">{{ $request->student->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $request->student->section?->name ?? 'No section' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">{{ $request->offering->subject->code }} · {{ $request->offering->section->name }}</td>
                                    <td class="px-6 py-4 text-slate-500">{{ $request->created_at->diffForHumans() }}</td>
                                    <td class="px-6 py-4 text-right"><x-enrollment-status :status="$request->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</x-layouts.app>
