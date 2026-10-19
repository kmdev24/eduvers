{{--
    The help assistant in the corner of the student pages. Answers how to use EduVers
    and nothing else - see config/assistant.php.
--}}
<div data-assistant data-assistant-url="{{ route('student.assistant') }}"
     class="pointer-events-none fixed inset-x-0 bottom-0 z-40 flex flex-col items-end gap-3 p-4 sm:p-6">

    {{-- The panel --}}
    <section data-assistant-panel hidden
             aria-label="EduVers help"
             class="pointer-events-auto flex w-[min(24rem,calc(100vw-2rem))] max-h-[min(32rem,70vh)] flex-col overflow-hidden rounded-2xl border border-ivory-300 bg-ivory shadow-elevated">

        <header class="flex items-start justify-between gap-3 border-b border-ivory-300 bg-ink-gradient px-5 py-4 text-ivory">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-luxe text-gold-400">EduVers help</p>
                <h2 class="font-serif text-lg font-semibold">How can I help?</h2>
            </div>
            <button type="button" data-assistant-close class="rounded-lg p-1.5 text-slate-300 transition hover:bg-white/10 hover:text-ivory" aria-label="Close help">
                <x-icon name="x" class="size-5" />
            </button>
        </header>

        <div data-assistant-log class="flex-1 space-y-3 overflow-y-auto px-5 py-4 text-sm" aria-live="polite">
            <div class="rounded-2xl rounded-tl-sm bg-ivory-200 px-4 py-3 text-ink-900">
                <p>Hello! I can show you around EduVers - your grades, subjects, lessons, quizzes and notifications.</p>
                <p class="mt-2 text-xs text-slate-500">I can't help with quiz questions or lesson content. Ask your teacher about those.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach (['Where are my grades?', 'What subjects do I have?', 'How do quizzes work?'] as $example)
                    <button type="button" data-assistant-example
                            class="rounded-full border border-ivory-300 bg-white px-3 py-1.5 text-xs text-slate-600 transition hover:border-gold-300 hover:text-ink-900">
                        {{ $example }}
                    </button>
                @endforeach
            </div>
        </div>

        <form data-assistant-form class="flex items-center gap-2 border-t border-ivory-300 bg-ivory-50/60 p-3">
            <label for="assistant-question" class="sr-only">Your question</label>
            <input id="assistant-question" data-assistant-input type="text" maxlength="500" autocomplete="off"
                   placeholder="Ask about using EduVers…" class="input flex-1 py-2.5 text-sm">
            <button type="submit" class="btn-gold shrink-0 px-4 py-2.5" aria-label="Send">
                <x-icon name="arrow-right" class="size-4" />
            </button>
        </form>
    </section>

    {{-- The button that opens it --}}
    <button type="button" data-assistant-open
            class="pointer-events-auto flex items-center gap-2 rounded-full bg-ink-gradient px-5 py-3.5 text-sm font-medium text-ivory shadow-elevated ring-1 ring-gold-500/30 transition hover:ring-gold-500/60 focus:ring-2 focus:ring-gold-500 focus:outline-none"
            aria-haspopup="dialog">
        <x-icon name="sparkles" class="size-5 text-gold-400" />
        <span>Need help?</span>
    </button>
</div>
