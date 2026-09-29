@use('App\Enums\TrackCategory')

<x-layouts.app title="Edit Strand">
    <x-page-header eyebrow="Tracks & strands" :title="'Edit '.$strand->name"
                   :description="$strand->sections_count.' section(s) and '.$strand->subjects_count.' subject(s) use this strand.'">
        <x-slot:actions>
            <a href="{{ route('developer.tracks.index') }}" class="btn-ghost">Back to tracks</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('developer.tracks.update', $strand) }}" class="mt-8 grid gap-6 xl:grid-cols-3">
        @csrf
        @method('PUT')

        <div class="card space-y-5 p-6 sm:p-8 xl:col-span-2">
            <x-field label="Name" name="name">
                <input id="name" name="name" type="text" value="{{ old('name', $strand->name) }}" required class="input">
            </x-field>
            <x-field label="Category" name="category"
                     :hint="$strand->sections_count > 0 ? 'Because sections use this strand, it can only move between SHS categories (not to or from College).' : null">
                <select id="category" name="category" class="input">
                    @foreach (TrackCategory::cases() as $category)
                        <option value="{{ $category->value }}" @selected(old('category', $strand->category->value) === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </x-field>
        </div>

        <div class="card-gold h-fit p-6">
            <button type="submit" class="btn-gold w-full py-3">Save changes</button>
            <a href="{{ route('developer.tracks.index') }}" class="mt-3 block text-center text-sm text-slate-500 hover:text-ink-900">Cancel</a>
        </div>
    </form>
</x-layouts.app>
