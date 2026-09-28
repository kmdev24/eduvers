<x-layouts.app title="Academic Terms">
    <x-page-header eyebrow="Academic structure" title="Academic terms"
                   description="Senior High School runs 3 terms per academic year. The current term is used across EduVers by default." />

    <div class="mt-8 grid gap-6 xl:grid-cols-3">

        {{-- Terms by year --}}
        <div class="space-y-6 xl:col-span-2">
            @forelse ($termsByYear as $year => $terms)
                <section class="card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-ivory-300 px-6 py-4">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-luxe text-champagne-dark">Academic year</p>
                            <h2 class="font-serif text-xl font-semibold text-ink-900">{{ $year }}</h2>
                        </div>
                        <span class="badge-slate">{{ $terms->count() }} {{ \Illuminate\Support\Str::plural('term', $terms->count()) }}</span>
                    </div>

                    <ul class="divide-y divide-ivory-200">
                        @foreach ($terms as $term)
                            <li @class(['flex flex-col gap-4 px-6 py-4 sm:flex-row sm:items-center', 'bg-gold-50/50' => $term->is_current])>
                                <div class="flex min-w-0 flex-1 items-center gap-4">
                                    <span @class([
                                        'flex size-11 shrink-0 items-center justify-center rounded-xl font-serif text-lg font-semibold',
                                        'bg-gold-gradient text-ink-900 shadow-gold' => $term->is_current,
                                        'bg-ivory-200 text-slate-500 ring-1 ring-ivory-300' => ! $term->is_current,
                                    ])>{{ $term->term_number }}</span>
                                    <div class="min-w-0">
                                        <p class="font-medium text-ink-900">
                                            {{ $term->name }}
                                            @if ($term->is_current)
                                                <span class="badge-gold ml-2">Current</span>
                                            @endif
                                        </p>
                                        <p class="text-sm text-slate-500">
                                            {{ $term->subjects_count }} subjects · {{ $term->lessons_count }} lessons · {{ $term->quizzes_count }} quizzes
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1 sm:justify-end">
                                    @unless ($term->is_current)
                                        <form method="POST" action="{{ route('developer.terms.current', $term) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn-outline-gold px-3 py-1.5 text-xs">Set as current</button>
                                        </form>
                                    @endunless
                                    <a href="{{ route('developer.terms.edit', $term) }}" class="icon-btn" title="Edit" aria-label="Edit {{ $term->name }}">
                                        <x-icon name="pencil" class="size-5" />
                                    </a>
                                    @php $inUse = $term->subjects_count + $term->lessons_count + $term->quizzes_count > 0; @endphp
                                    <form method="POST" action="{{ route('developer.terms.destroy', $term) }}" data-confirm="Delete {{ $term->name }} ({{ $year }})?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="icon-btn-danger" aria-label="Delete {{ $term->name }}"
                                                title="{{ $term->is_current ? 'The current term cannot be deleted' : ($inUse ? 'In use by subjects, lessons or quizzes' : 'Delete') }}"
                                                @disabled($term->is_current || $inUse)>
                                            <x-icon name="trash" class="size-5" />
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <div class="card">
                    <x-empty-state icon="calendar" title="No terms yet" message="Add an academic year on the right to create its terms." />
                </div>
            @endforelse
        </div>

        {{-- Sidebar forms --}}
        <div class="space-y-6">
            <div class="relative overflow-hidden rounded-2xl bg-ink-gradient p-6 text-ivory shadow-elevated">
                <div class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-gold-500/20 blur-2xl"></div>
                <p class="relative text-xs font-semibold uppercase tracking-luxe text-gold-400">Current term</p>
                @if ($currentTerm)
                    <p class="relative mt-3 font-serif text-3xl font-semibold">{{ $currentTerm->name }}</p>
                    <p class="relative mt-1 text-sm text-slate-300">Academic Year {{ $currentTerm->academic_year ?? '—' }}</p>
                @else
                    <p class="relative mt-3 text-sm text-slate-300">No current term is set.</p>
                @endif
            </div>

            <form method="POST" action="{{ route('developer.terms.year') }}" class="card-gold space-y-4 p-6">
                @csrf
                <div>
                    <h2 class="font-serif text-lg font-semibold">Add an academic year</h2>
                    <p class="text-sm text-slate-500">Creates 1st, 2nd and 3rd Term in one step.</p>
                </div>
                <x-field label="Academic year" name="academic_year" for="year_academic_year" bag="year">
                    <input id="year_academic_year" name="academic_year" type="text" class="input" placeholder="2027-2028"
                           value="{{ $errors->year->any() ? old('academic_year') : $nextYear }}">
                </x-field>
                <x-field label="Number of terms" name="terms" for="year_terms" bag="year">
                    <select id="year_terms" name="terms" class="input">
                        @foreach ([3 => '3 terms (SHS)', 2 => '2 terms (semesters)', 4 => '4 terms', 1 => '1 term'] as $n => $label)
                            <option value="{{ $n }}" @selected((int) old('terms', 3) === $n)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <button type="submit" class="btn-gold w-full"><x-icon name="plus" class="size-4" /> Create terms</button>
            </form>

            <form method="POST" action="{{ route('developer.terms.store') }}" class="card space-y-4 p-6">
                @csrf
                <div>
                    <h2 class="font-serif text-lg font-semibold">Add a single term</h2>
                    <p class="text-sm text-slate-500">For special or summer terms.</p>
                </div>
                <x-field label="Name" name="name" for="term_name" bag="term">
                    <input id="term_name" name="name" type="text" class="input" placeholder="Summer Term"
                           value="{{ $errors->term->any() ? old('name') : '' }}">
                </x-field>
                <div class="grid grid-cols-2 gap-3">
                    <x-field label="Academic year" name="academic_year" for="term_academic_year" bag="term">
                        <input id="term_academic_year" name="academic_year" type="text" class="input" placeholder="2026-2027"
                               value="{{ $errors->term->any() ? old('academic_year') : '' }}">
                    </x-field>
                    <x-field label="Term no." name="term_number" for="term_number" bag="term">
                        <input id="term_number" name="term_number" type="number" min="1" max="4" class="input"
                               value="{{ $errors->term->any() ? old('term_number') : '' }}">
                    </x-field>
                </div>
                <button type="submit" class="btn-ghost w-full"><x-icon name="plus" class="size-4" /> Add term</button>
            </form>
        </div>
    </div>
</x-layouts.app>
