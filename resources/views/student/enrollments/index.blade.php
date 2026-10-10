@use('App\Enums\EnrollmentStatus')

<x-layouts.app title="Apply for a subject">
    <x-page-header eyebrow="Learning" title="Apply for a subject"
                   :description="$student->section ? 'Join a class of your course ('.$student->section->trackStrand->name.') that is taught in another section. An admin approves each application.' : 'You are not enrolled in a section yet.'" />

    @php $requestFor = $requests->keyBy('subject_teacher_id'); @endphp

    {{-- Classes open for applications --}}
    <div class="card mt-8">
        <div class="border-b border-ivory-300 px-6 py-4">
            <h2 class="font-serif text-xl font-semibold text-ink-900">Classes you can apply for</h2>
        </div>

        @if ($classes->isEmpty())
            <x-empty-state icon="stack" title="No classes to apply for"
                           :message="$student->section ? 'Every subject of your course is already in your list, or no other section offers one yet.' : 'Please contact the Registrar\'s Office to be enrolled in a section.'" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="px-6 py-3">Subject</th>
                            <th class="px-6 py-3">Section</th>
                            <th class="px-6 py-3">Teacher</th>
                            <th class="px-6 py-3 text-right">Apply</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ivory-200">
                        @foreach ($classes as $class)
                            @php $existing = $requestFor->get($class->id); @endphp
                            <tr class="transition hover:bg-ivory-50">
                                <td class="px-6 py-4">
                                    <p class="text-xs font-semibold uppercase tracking-luxe text-champagne-dark">{{ $class->subject->code }}</p>
                                    <p class="font-medium text-ink-900">{{ $class->subject->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $class->subject->gradeLevel?->name }}{{ $class->subject->isCore() ? ' · Core subject' : '' }}</p>
                                </td>
                                <td class="px-6 py-4 text-slate-600">{{ $class->section->name }}</td>
                                <td class="px-6 py-4 text-slate-600">{{ $class->teacher->name }}</td>
                                <td class="px-6 py-4 text-right">
                                    @if ($existing && $existing->status !== EnrollmentStatus::Rejected)
                                        <x-enrollment-status :status="$existing->status" />
                                    @else
                                        <form method="POST" action="{{ route('student.enrollments.store') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="class_id" value="{{ $class->id }}">
                                            <button type="submit" class="btn-outline-gold px-4 py-2">
                                                <x-icon name="plus" class="size-4" /> {{ $existing ? 'Apply again' : 'Apply' }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- My applications --}}
    @if ($requests->isNotEmpty())
        <div class="card mt-8">
            <div class="border-b border-ivory-300 px-6 py-4">
                <h2 class="font-serif text-xl font-semibold text-ink-900">My applications</h2>
            </div>
            <ul class="divide-y divide-ivory-200">
                @foreach ($requests as $request)
                    <li class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-medium text-ink-900">{{ $request->offering->subject->code }} · {{ $request->offering->subject->name }}</p>
                            <p class="text-xs text-slate-500">{{ $request->offering->section->name }} · {{ $request->offering->teacher->name }} · sent {{ $request->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <x-enrollment-status :status="$request->status" />
                            @if ($request->status === EnrollmentStatus::Approved)
                                <a href="{{ route('student.subjects.show', $request->offering->subject) }}" class="text-sm font-medium text-gold-700 hover:text-gold-900">Open</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-layouts.app>
