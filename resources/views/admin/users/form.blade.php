@use('App\Enums\UserRole')
@use('App\Enums\TeacherType')

@php
    $editing = $user->exists;
    $role    = old('role', $user->role?->value ?? UserRole::Student->value);
    $roleCards = [
        UserRole::Student->value    => ['icon' => 'book-open',    'desc' => 'Takes lessons and quizzes'],
        UserRole::Teacher->value    => ['icon' => 'academic-cap', 'desc' => 'Teaches classes and posts content'],
        UserRole::Admin->value      => ['icon' => 'shield',       'desc' => 'Full administrator access'],
        UserRole::Developer->value  => ['icon' => 'code-bracket', 'desc' => 'Like admin, plus API and email settings'],
    ];
@endphp

<x-layouts.app :title="$editing ? 'Edit User' : 'Add User'">
    <x-page-header eyebrow="User management"
                   :title="$editing ? 'Edit '.$user->name : 'Add a new user'"
                   :description="$editing ? 'Update this account. Leave the password blank to keep the current one.' : 'Create an account and set the password the user will sign in with.'">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn-ghost">Back to users</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" data-role-form
          action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}"
          class="mt-8 grid gap-6 xl:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- Account details --}}
        <div class="card space-y-6 p-6 sm:p-8 xl:col-span-2">
            <div>
                <h2 class="font-serif text-lg font-semibold">Account details</h2>
                <p class="text-sm text-slate-500">Basic information and role</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Full name" name="name">
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="input" placeholder="Juan Dela Cruz">
                </x-field>
                <x-field label="Email address" name="email">
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="input" placeholder="juan@movers.edu.ph">
                </x-field>
            </div>

            <div>
                <p class="mb-2 text-sm font-medium text-ink-800">Role</p>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($roleCards as $value => $card)
                        <label class="relative cursor-pointer">
                            <input type="radio" name="role" value="{{ $value }}" class="peer sr-only" @checked($role === $value)>
                            <span class="flex h-full flex-col rounded-xl border border-ivory-300 bg-white p-4 transition hover:border-gold-300
                                         peer-checked:border-gold-500 peer-checked:bg-gold-50/40 peer-checked:ring-2 peer-checked:ring-gold-500/20
                                         peer-focus-visible:ring-2 peer-focus-visible:ring-gold-500/40">
                                <x-icon :name="$card['icon']" class="size-5 text-gold-600" />
                                <span class="mt-3 font-medium text-ink-900">{{ UserRole::from($value)->label() }}</span>
                                <span class="mt-0.5 text-xs text-slate-500">{{ $card['desc'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('role') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Teacher only --}}
            <div data-show-for-role="teacher">
                <x-field label="Teacher type" name="teacher_type">
                    <select id="teacher_type" name="teacher_type" class="input">
                        <option value="">Select a type…</option>
                        @foreach (TeacherType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('teacher_type', $user->teacher_type?->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>

            {{-- Student only: year level and strand narrow the section list below them --}}
            @php
                $chosenSection = $sections->firstWhere('id', (int) old('section_id', $user->section_id));
                $chosenLevel   = (int) old('grade_level_id', $user->grade_level_id ?? $chosenSection?->grade_level_id);
                $chosenStrand  = (int) old('track_strand_id', $user->track_strand_id ?? $chosenSection?->track_strand_id);
            @endphp
            <div data-show-for-role="student" class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field label="Year level" name="grade_level_id" hint="Senior High: Grade 11–12. College: 1st–4th Year. Saved on the student, so it holds even before they have a section.">
                        <select id="grade_level_id" name="grade_level_id" class="input" data-level-select="track_strand_id">
                            <option value="">Not set</option>
                            @foreach ($levels as $level)
                                <option value="{{ $level->id }}" data-level-type="{{ $level->level_type->value }}"
                                        @selected($chosenLevel === $level->id)>
                                    {{ $level->name }} · {{ $level->level_type->label() }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Strand / course" name="track_strand_id">
                        <select id="track_strand_id" name="track_strand_id" class="input">
                            <option value="">Not set</option>
                            @foreach ($strands as $strand)
                                <option value="{{ $strand->id }}" data-category="{{ $strand->category->value }}"
                                        @selected($chosenStrand === $strand->id)>
                                    {{ $strand->name }} · {{ $strand->category->label() }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>
                </div>

                <x-field label="Section" name="section_id" hint="Only sections of the chosen year level and strand are listed. Full sections are disabled, and you can leave this empty and enroll the student later.">
                    <select id="section_id" name="section_id" class="input"
                            data-depends="grade_level_id=gradeLevelId,track_strand_id=strandId">
                        <option value="">Not enrolled yet</option>
                        @foreach ($sections->groupBy(fn ($s) => $s->gradeLevel->name) as $levelName => $group)
                            <optgroup label="{{ $levelName }}">
                                @foreach ($group as $section)
                                    @php
                                        $isCurrent = (int) $user->section_id === $section->id;
                                        $isFull    = $section->students_count >= $section->capacity;
                                    @endphp
                                    <option value="{{ $section->id }}"
                                            data-grade-level-id="{{ $section->grade_level_id }}"
                                            data-strand-id="{{ $section->track_strand_id }}"
                                            @selected((string) old('section_id', $user->section_id) === (string) $section->id)
                                            @disabled($isFull && ! $isCurrent)>
                                        {{ $section->name }} · {{ $section->trackStrand->name }} ({{ $section->students_count }}/{{ $section->capacity }}){{ $isFull && ! $isCurrent ? ' · Full' : '' }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </x-field>

                @if ($sections->isEmpty())
                    <p class="text-sm text-amber-700">No sections exist yet. <a href="{{ route('admin.sections.create') }}" class="font-medium underline">Create one</a> first.</p>
                @endif
            </div>
        </div>

        {{-- Password & save --}}
        <div class="space-y-6">
            <div class="card space-y-5 p-6 sm:p-8">
                <div>
                    <h2 class="font-serif text-lg font-semibold">{{ $editing ? 'Reset password' : 'Initial password' }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ $editing ? 'Only fill this in to set a new password.' : 'Share this with the user so they can sign in.' }}
                    </p>
                </div>

                <x-field label="Password" name="password" hint="At least 8 characters.">
                    <div class="relative">
                        <input id="password" name="password" type="password" autocomplete="new-password" class="input pr-12"
                               @if (! $editing) required @endif placeholder="{{ $editing ? 'Leave blank to keep' : '••••••••' }}">
                        <button type="button" data-toggle-password="password" class="icon-btn absolute top-1/2 right-1.5 -translate-y-1/2" aria-label="Show or hide password">
                            <x-icon name="eye" class="size-4" />
                        </button>
                    </div>
                </x-field>

                <button type="button" data-generate-password="password" class="btn-outline-gold w-full">
                    <x-icon name="sparkles" class="size-4" /> Generate a password
                </button>
            </div>

            <div class="card-gold p-6">
                <button type="submit" class="btn-gold w-full py-3">
                    {{ $editing ? 'Save changes' : 'Create user' }}
                </button>
                <a href="{{ route('admin.users.index') }}" class="mt-3 block text-center text-sm text-slate-500 hover:text-ink-900">Cancel</a>
            </div>
        </div>
    </form>
</x-layouts.app>
