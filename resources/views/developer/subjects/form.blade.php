@php $editing = $subject->exists; @endphp

<x-layouts.app :title="$editing ? 'Edit Subject' : 'New Subject'">
    <x-page-header eyebrow="Curriculum"
                   :title="$editing ? 'Edit '.$subject->code : 'Create a subject'"
                   description="Core subjects are offered to every section in the grade level. Pick a strand to limit it to that strand's sections.">
        <x-slot:actions>
            <a href="{{ route('developer.subjects.index') }}" class="btn-ghost">Back to subjects</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST"
          action="{{ $editing ? route('developer.subjects.update', $subject) : route('developer.subjects.store') }}"
          class="mt-8 grid gap-6 xl:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card space-y-6 p-6 sm:p-8 xl:col-span-2">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field label="Subject code" name="code" hint="Must be unique, e.g. CORE-OC11">
                    <input id="code" name="code" type="text" value="{{ old('code', $subject->code) }}" required maxlength="32" class="input uppercase" placeholder="CORE-OC11">
                </x-field>
                <x-field label="Subject name" name="name" class="sm:col-span-2">
                    <input id="name" name="name" type="text" value="{{ old('name', $subject->name) }}" required class="input" placeholder="Oral Communication">
                </x-field>
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <x-field label="Grade level" name="grade_level_id">
                    <select id="grade_level_id" name="grade_level_id" required class="input" data-level-select="track_strand_id">
                        <option value="">Select…</option>
                        @foreach ($gradeLevels->groupBy(fn ($l) => $l->level_type->label()) as $type => $levels)
                            <optgroup label="{{ $type }}">
                                @foreach ($levels as $level)
                                    <option value="{{ $level->id }}" data-level-type="{{ $level->level_type->value }}"
                                            @selected((string) old('grade_level_id', $subject->grade_level_id) === (string) $level->id)>{{ $level->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Track / strand" name="track_strand_id">
                    <select id="track_strand_id" name="track_strand_id" class="input">
                        <option value="">Core (all strands)</option>
                        @foreach ($strands->groupBy(fn ($s) => $s->category->label()) as $category => $group)
                            <optgroup label="{{ $category }}">
                                @foreach ($group as $strand)
                                    <option value="{{ $strand->id }}" data-category="{{ $strand->category->value }}"
                                            @selected((string) old('track_strand_id', $subject->track_strand_id) === (string) $strand->id)>{{ $strand->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Term" name="academic_term_id">
                    <select id="academic_term_id" name="academic_term_id" required class="input">
                        <option value="">Select…</option>
                        @foreach ($terms as $term)
                            <option value="{{ $term->id }}" @selected((string) old('academic_term_id', $subject->academic_term_id) === (string) $term->id)>
                                {{ $term->name }}{{ $term->academic_year ? ' · '.$term->academic_year : '' }}{{ $term->is_current ? ' (current)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </x-field>
            </div>

            @if ($editing)
                <p class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <x-icon name="shield" class="mt-0.5 size-4 shrink-0" />
                    If you change the grade level or strand, teacher assignments to sections that no longer take this subject are removed.
                </p>
            @endif
        </div>

        <div class="card-gold h-fit p-6">
            <button type="submit" class="btn-gold w-full py-3">{{ $editing ? 'Save changes' : 'Create & assign teachers' }}</button>
            <a href="{{ route('developer.subjects.index') }}" class="mt-3 block text-center text-sm text-slate-500 hover:text-ink-900">Cancel</a>
        </div>
    </form>
</x-layouts.app>
