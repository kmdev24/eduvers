<x-layouts.app title="New Quiz">
    <x-page-header eyebrow="Quizzes" title="Create a quiz"
                   description="Set the basics first. You'll add questions and choose sections on the next screen.">
        <x-slot:actions>
            <a href="{{ route('teacher.quizzes.index') }}" class="btn-ghost">Cancel</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('teacher.quizzes.store') }}" class="mt-8 grid gap-6 xl:grid-cols-3">
        @csrf
        <div class="card space-y-6 p-6 sm:p-8 xl:col-span-2">
            <x-field label="Quiz title" name="title">
                <input id="title" name="title" type="text" value="{{ old('title') }}" required class="input" placeholder="Quiz 1: Kitchen Safety">
            </x-field>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field label="Subject" name="subject_id" class="sm:col-span-2">
                    <select id="subject_id" name="subject_id" required class="input">
                        <option value="">Select…</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected((string) old('subject_id', $subjectId) === (string) $subject->id)>{{ $subject->code }} · {{ $subject->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Passing score (%)" name="passing_score">
                    <input id="passing_score" name="passing_score" type="number" min="1" max="100" value="{{ old('passing_score', 75) }}" required class="input">
                </x-field>
            </div>
            <x-field label="Instructions" name="description" hint="Optional. Shown to students before they start.">
                <textarea id="description" name="description" rows="4" class="input" placeholder="Read each question carefully. You can only take this quiz once.">{{ old('description') }}</textarea>
            </x-field>
        </div>
        <div class="card-gold h-fit p-6">
            <button type="submit" class="btn-gold w-full py-3">Continue to questions <x-icon name="arrow-right" class="size-4" /></button>
        </div>
    </form>
</x-layouts.app>
