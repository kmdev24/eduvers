@use('App\Support\ClassRoster')

{{--
    Every student against one class: who is already in it, and who can still be added.
    Used by the teacher's "Add students" page and the admin's.
--}}
@props([
    'class',                       // SubjectTeacher the roster is measured against
    'students',                    // paginator of User
    'enrollments',                 // this class's ClassEnrollment rows keyed by student id
    'action',                      // route the Add button posts to
    'addLabel' => 'Add',
])

@if ($students->isEmpty())
    <x-empty-state icon="users" title="No students found"
                   message="No student matches that name or email." />
@else
    <ul class="divide-y divide-ivory-200">
        @foreach ($students as $student)
            @php $reason = ClassRoster::blockedReason($student, $class, $enrollments->get($student->id)); @endphp
            <li class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p @class(['font-medium', 'text-ink-900' => ! $reason, 'text-slate-400' => $reason])>{{ $student->name }}</p>
                    <p class="text-xs break-all text-slate-500">
                        {{ $student->email }} · {{ $student->section?->name ?? 'No section' }}
                    </p>
                </div>

                @if ($reason)
                    <span class="shrink-0 rounded-full bg-ivory-200 px-3 py-1 text-xs font-medium text-slate-500 ring-1 ring-ivory-300">
                        {{ ucfirst($reason) }}
                    </span>
                @else
                    <form method="POST" action="{{ $action }}" class="shrink-0">
                        @csrf
                        <input type="hidden" name="class_id" value="{{ $class->id }}">
                        <input type="hidden" name="student_id" value="{{ $student->id }}">
                        <button type="submit" class="btn-outline-gold px-4 py-2">
                            <x-icon name="user-plus" class="size-4" /> {{ $addLabel }}
                        </button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>

    <div class="border-t border-ivory-300 px-6 py-4">
        {{ $students->links('partials.pagination') }}
    </div>
@endif
