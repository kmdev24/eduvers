<x-layouts.app title="Edit Term">
    <x-page-header eyebrow="Academic terms" :title="'Edit '.$term->name">
        <x-slot:actions>
            <a href="{{ route('developer.terms.index') }}" class="btn-ghost">Back to terms</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('developer.terms.update', $term) }}" class="mt-8 grid gap-6 xl:grid-cols-3">
        @csrf
        @method('PUT')

        <div class="card space-y-5 p-6 sm:p-8 xl:col-span-2">
            <x-field label="Name" name="name">
                <input id="name" name="name" type="text" value="{{ old('name', $term->name) }}" required class="input">
            </x-field>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Academic year" name="academic_year" hint="Format: 2026-2027">
                    <input id="academic_year" name="academic_year" type="text" value="{{ old('academic_year', $term->academic_year) }}" required class="input">
                </x-field>
                <x-field label="Term number" name="term_number">
                    <input id="term_number" name="term_number" type="number" min="1" max="4" value="{{ old('term_number', $term->term_number) }}" required class="input">
                </x-field>
            </div>
        </div>

        <div class="card-gold h-fit p-6">
            <button type="submit" class="btn-gold w-full py-3">Save changes</button>
            <a href="{{ route('developer.terms.index') }}" class="mt-3 block text-center text-sm text-slate-500 hover:text-ink-900">Cancel</a>
        </div>
    </form>
</x-layouts.app>
