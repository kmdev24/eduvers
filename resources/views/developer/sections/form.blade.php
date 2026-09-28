@php $editing = $section->exists; @endphp

<x-layouts.app :title="$editing ? 'Edit Section' : 'New Section'">
    <x-page-header eyebrow="Academic structure"
                   :title="$editing ? 'Edit '.$section->name : 'Create a section'"
                   description="Choose the grade level first. Only strands that fit that level can be selected.">
        <x-slot:actions>
            <a href="{{ route('developer.sections.index') }}" class="btn-ghost">Back to sections</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST"
          action="{{ $editing ? route('developer.sections.update', $section) : route('developer.sections.store') }}"
          class="mt-8 grid gap-6 xl:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card space-y-6 p-6 sm:p-8 xl:col-span-2">
            <x-field label="Section name" name="name" hint="For example: ICT 11-A, ABM 12-B, HRS 1-A">
                <input id="name" name="name" type="text" value="{{ old('name', $section->name) }}" required class="input" placeholder="ICT 11-A">
            </x-field>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Grade level" name="grade_level_id">
                    <select id="grade_level_id" name="grade_level_id" required class="input" data-level-select="track_strand_id">
                        <option value="">Select a level…</option>
                        @foreach ($gradeLevels->groupBy(fn ($l) => $l->level_type->label()) as $type => $levels)
                            <optgroup label="{{ $type }}">
                                @foreach ($levels as $level)
                                    <option value="{{ $level->id }}" data-level-type="{{ $level->level_type->value }}"
                                            @selected((string) old('grade_level_id', $section->grade_level_id) === (string) $level->id)>{{ $level->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Track / strand" name="track_strand_id">
                    <select id="track_strand_id" name="track_strand_id" required class="input">
                        <option value="">Select a strand…</option>
                        @foreach ($strands->groupBy(fn ($s) => $s->category->label()) as $category => $group)
                            <optgroup label="{{ $category }}">
                                @foreach ($group as $strand)
                                    <option value="{{ $strand->id }}" data-category="{{ $strand->category->value }}"
                                            @selected((string) old('track_strand_id', $section->track_strand_id) === (string) $strand->id)>{{ $strand->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </x-field>
            </div>

            <x-field label="Maximum capacity" name="capacity"
                     :hint="$editing ? 'Currently '.$section->students_count.' student(s) enrolled. Capacity cannot go below that.' : 'The most students this section can hold.'">
                <input id="capacity" name="capacity" type="number" min="1" max="200" value="{{ old('capacity', $section->capacity) }}" required class="input sm:w-48">
            </x-field>

            <p class="text-sm text-slate-500">
                Need a strand that isn't listed (for example TVL)? Add it under
                <a href="{{ route('developer.tracks.index') }}" class="font-medium text-gold-700 underline decoration-gold-300 underline-offset-2">Tracks &amp; strands</a>.
            </p>
        </div>

        <div class="card-gold h-fit p-6">
            <button type="submit" class="btn-gold w-full py-3">{{ $editing ? 'Save changes' : 'Create section' }}</button>
            <a href="{{ route('developer.sections.index') }}" class="mt-3 block text-center text-sm text-slate-500 hover:text-ink-900">Cancel</a>
        </div>
    </form>
</x-layouts.app>
