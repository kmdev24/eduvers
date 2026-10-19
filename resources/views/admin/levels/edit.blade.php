@use('App\Enums\LevelType')

<x-layouts.app title="Edit Year Level">
    <x-page-header eyebrow="Year levels" :title="'Edit '.$level->name"
                   :description="$level->sections_count.' section(s) and '.$level->subjects_count.' subject(s) use this year level.'">
        <x-slot:actions>
            <a href="{{ route('admin.levels.index') }}" class="btn-ghost">Back to year levels</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('admin.levels.update', $level) }}" class="mt-8 grid gap-6 xl:grid-cols-3">
        @csrf
        @method('PUT')

        <div class="card space-y-5 p-6 sm:p-8 xl:col-span-2">
            <x-field label="Name" name="name">
                <input id="name" name="name" type="text" value="{{ old('name', $level->name) }}" required class="input">
            </x-field>
            <x-field label="Programme" name="level_type"
                     :hint="$level->sections_count > 0 ? 'Sections use this level, so it can no longer move between Senior High School and College.' : null">
                <select id="level_type" name="level_type" class="input">
                    @foreach (LevelType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('level_type', $level->level_type->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </x-field>
        </div>

        <div class="card-gold h-fit p-6">
            <button type="submit" class="btn-gold w-full py-3">Save changes</button>
            <a href="{{ route('admin.levels.index') }}" class="mt-3 block text-center text-sm text-slate-500 hover:text-ink-900">Cancel</a>
        </div>
    </form>
</x-layouts.app>
