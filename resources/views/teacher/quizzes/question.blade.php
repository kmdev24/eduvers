<x-layouts.app title="Edit Question">
    <x-page-header :eyebrow="$quiz->title" title="Edit question">
        <x-slot:actions>
            <a href="{{ route('teacher.quizzes.edit', $quiz) }}#questions" class="btn-ghost">Back to builder</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('teacher.quizzes.questions.update', [$quiz, $question]) }}" class="mt-8 grid gap-6 xl:grid-cols-3">
        @csrf
        @method('PUT')
        <div class="card space-y-5 p-6 sm:p-8 xl:col-span-2">
            @include('teacher.quizzes._options', ['question' => $question, 'bag' => 'default', 'prefix' => ''])
        </div>
        <div class="card-gold h-fit p-6">
            <button type="submit" class="btn-gold w-full py-3">Save question</button>
            <a href="{{ route('teacher.quizzes.edit', $quiz) }}#questions" class="mt-3 block text-center text-sm text-slate-500 hover:text-ink-900">Cancel</a>
        </div>
    </form>
</x-layouts.app>
