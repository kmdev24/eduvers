@use('App\Enums\LevelType')

<x-layouts.app title="Year Levels">
    <x-page-header eyebrow="Academic structure" title="Year levels"
                   description="Grade 11 and 12 for Senior High School, 1st to 4th Year for College. Sections and subjects belong to a year level, so it has to exist before students can be enrolled in it. These levels are what the sign-in page shows visitors." />

    <div class="mt-8 grid gap-6 xl:grid-cols-3">

        {{-- Levels by programme --}}
        <div class="grid gap-6 md:grid-cols-2 xl:col-span-2">
            @foreach ($programmes as $group)
                @php $type = $group['type']; @endphp
                <section class="card flex flex-col">
                    <div class="border-b border-ivory-300 px-6 py-5">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="font-serif text-xl font-semibold text-ink-900">{{ $type->label() }}</h2>
                            <span class="{{ $type === LevelType::College ? 'badge-gold' : 'badge-slate' }}">{{ $group['levels']->count() }} levels</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $type === LevelType::College ? 'Year levels of a College course, e.g. 1st Year to 4th Year.' : 'Grade levels of Senior High School, e.g. Grade 11 and Grade 12.' }}
                        </p>
                    </div>

                    <ul class="flex-1 divide-y divide-ivory-200">
                        @forelse ($group['levels'] as $level)
                            @php $inUse = $level->sections_count + $level->subjects_count > 0; @endphp
                            <li class="flex items-center gap-3 px-6 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-ink-900">{{ $level->name }}</p>
                                    <p class="text-xs {{ $level->sections_count === 0 ? 'text-amber-700' : 'text-slate-500' }}">
                                        {{ $level->sections_count }} sections · {{ $level->subjects_count }} subjects
                                        @if ($level->sections_count === 0) · no section yet @endif
                                    </p>
                                </div>
                                <a href="{{ route('admin.levels.edit', $level) }}" class="icon-btn" title="Edit" aria-label="Edit {{ $level->name }}">
                                    <x-icon name="pencil" class="size-4" />
                                </a>
                                <form method="POST" action="{{ route('admin.levels.destroy', $level) }}" data-confirm="Delete {{ $level->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn-danger" aria-label="Delete {{ $level->name }}"
                                            title="{{ $inUse ? 'In use by sections or subjects' : 'Delete' }}"
                                            @disabled($inUse)>
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                </form>
                            </li>
                        @empty
                            <li class="px-6 py-8 text-center text-sm text-slate-400">No year levels yet.</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>

        {{-- Add level --}}
        <form method="POST" action="{{ route('admin.levels.store') }}" class="card-gold h-fit space-y-4 p-6">
            @csrf
            <div>
                <h2 class="font-serif text-lg font-semibold">Add a year level</h2>
                <p class="text-sm text-slate-500">{{ $total }} {{ \Illuminate\Support\Str::plural('level', $total) }} in total</p>
            </div>
            <x-field label="Name" name="name" for="level_name" bag="level" hint="e.g. Grade 12, 3rd Year, 4th Year">
                <input id="level_name" name="name" type="text" class="input" placeholder="3rd Year"
                       value="{{ $errors->level->any() ? old('name') : '' }}">
            </x-field>
            <x-field label="Programme" name="level_type" for="level_type" bag="level">
                <select id="level_type" name="level_type" class="input">
                    @foreach (LevelType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('level_type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </x-field>
            <button type="submit" class="btn-gold w-full"><x-icon name="plus" class="size-4" /> Add year level</button>
            <p class="text-xs leading-relaxed text-slate-500">
                A new level has no sections yet. Create one under <a href="{{ route('admin.sections.index') }}" class="underline">Sections</a> so students can be enrolled in it.
            </p>
        </form>
    </div>
</x-layouts.app>
