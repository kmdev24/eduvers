<x-layouts.app title="Add students">
    <x-page-header eyebrow="Administration" title="Add students to a class"
                   description="Put a student into any class straight away. You are the approver, so there is no request to approve afterwards." />

    @if ($classes->isEmpty())
        <div class="card mt-8">
            <x-empty-state icon="building" title="No classes yet"
                           message="Classes appear once there is a section and a subject for its year level." />
        </div>
    @else
        <div class="card mt-8">
            <form method="GET" action="{{ route('admin.class-students.index') }}" class="flex flex-col gap-3 border-b border-ivory-300 p-5 md:flex-row md:items-center">
                <select name="class" class="input md:w-96" aria-label="Class">
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($selected?->id === $class->id)>
                            {{ $class->section->name }} · {{ $class->subject->code }} · {{ $class->subject->name }}
                        </option>
                    @endforeach
                </select>
                <div class="relative flex-1">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search students by name or email" class="input pl-10" aria-label="Search students">
                </div>
                <button type="submit" class="btn-dark">Search</button>
            </form>

            <x-class-roster :class="$selected" :students="$students" :enrollments="$enrollments"
                            :action="route('admin.class-students.store')"
                            :add-label="'Add to '.$selected->subject->code" />
        </div>
    @endif
</x-layouts.app>
