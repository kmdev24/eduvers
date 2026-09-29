{{-- Question editor fields. Expects $question (QuizQuestion|null), $bag (error bag name), $prefix (id prefix). --}}
@php
    $errs    = $errors->getBag($bag);
    $useOld  = $bag === 'default' || $errs->any();
    $options = $question?->options_json ?? [];
    $correct = $useOld ? old('correct_answer', $question?->correct_answer) : $question?->correct_answer;
@endphp

<x-field label="Question" name="question" :for="$prefix.'question'" :bag="$bag">
    <textarea id="{{ $prefix }}question" name="question" rows="3" required class="input"
              placeholder="What is the safe internal temperature for cooked chicken?">{{ $useOld ? old('question', $question?->question) : $question?->question }}</textarea>
</x-field>

<div>
    <p class="mb-2 text-sm font-medium text-ink-800">Options <span class="font-normal text-slate-500">· select the correct answer</span></p>
    <div class="space-y-2.5">
        @foreach (\App\Http\Controllers\Teacher\QuizQuestionController::KEYS as $key)
            <div class="flex items-center gap-3">
                <label class="relative shrink-0 cursor-pointer" title="Mark {{ $key }} as correct">
                    <input type="radio" name="correct_answer" value="{{ $key }}" class="peer sr-only" @checked($correct === $key)>
                    <span class="flex size-10 items-center justify-center rounded-xl border border-ivory-300 bg-white font-semibold text-slate-500 transition
                                 hover:border-gold-300 peer-checked:border-gold-500 peer-checked:bg-gold-gradient peer-checked:text-ink-900 peer-checked:shadow-gold
                                 peer-focus-visible:ring-2 peer-focus-visible:ring-gold-500/40">{{ $key }}</span>
                </label>
                <input type="text" name="options[{{ $key }}]" aria-label="Option {{ $key }}"
                       value="{{ $useOld ? old('options.'.$key, $options[$key] ?? '') : ($options[$key] ?? '') }}"
                       class="input" placeholder="{{ in_array($key, ['A', 'B']) ? 'Required' : 'Optional' }}" @required(in_array($key, ['A', 'B']))>
            </div>
            @if ($errs->has('options.'.$key))
                <p class="pl-13 text-sm text-red-600">{{ $errs->first('options.'.$key) }}</p>
            @endif
        @endforeach
    </div>
    @if ($errs->has('correct_answer'))
        <p class="mt-2 text-sm text-red-600">{{ $errs->first('correct_answer') }}</p>
    @endif
</div>
