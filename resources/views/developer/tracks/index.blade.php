@use('App\Enums\TrackCategory')

<x-layouts.app title="Tracks & Strands">
    <x-page-header eyebrow="Academic structure" title="Tracks & strands"
                   description="Strands and programs, grouped by category. Sections and strand-specific subjects are linked to these." />

    <div class="mt-8 grid gap-6 xl:grid-cols-3">

        {{-- Categories --}}
        <div class="grid gap-6 md:grid-cols-2 xl:col-span-2">
            @foreach ($categories as $group)
                @php $category = $group['category']; @endphp
                <section class="card flex flex-col">
                    <div class="border-b border-ivory-300 px-6 py-5">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="font-serif text-xl font-semibold text-ink-900">{{ $category->label() }}</h2>
                            <span class="{{ $category->isCollege() ? 'badge-gold' : 'badge-slate' }}">{{ $category->isCollege() ? 'College' : 'Senior High' }}</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $category->description() }}</p>
                    </div>

                    <ul class="flex-1 divide-y divide-ivory-200">
                        @forelse ($group['strands'] as $strand)
                            <li class="flex items-center gap-3 px-6 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-ink-900">{{ $strand->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $strand->sections_count }} sections · {{ $strand->subjects_count }} subjects</p>
                                </div>
                                <a href="{{ route('developer.tracks.edit', $strand) }}" class="icon-btn" title="Edit" aria-label="Edit {{ $strand->name }}">
                                    <x-icon name="pencil" class="size-4" />
                                </a>
                                <form method="POST" action="{{ route('developer.tracks.destroy', $strand) }}" data-confirm="Delete {{ $strand->name }}? Its subjects will become core subjects.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn-danger" aria-label="Delete {{ $strand->name }}"
                                            title="{{ $strand->sections_count > 0 ? 'In use by sections' : 'Delete' }}"
                                            @disabled($strand->sections_count > 0)>
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                </form>
                            </li>
                        @empty
                            <li class="px-6 py-8 text-center text-sm text-slate-400">No strands in this category yet.</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>

        {{-- Add strand --}}
        <form method="POST" action="{{ route('developer.tracks.store') }}" class="card-gold h-fit space-y-4 p-6">
            @csrf
            <div>
                <h2 class="font-serif text-lg font-semibold">Add a track / strand</h2>
                <p class="text-sm text-slate-500">{{ $total }} {{ \Illuminate\Support\Str::plural('strand', $total) }} in total</p>
            </div>
            <x-field label="Name" name="name" for="strand_name" bag="strand" hint="e.g. TVL-HE, TVL-ICT, STEM, HUMSS">
                <input id="strand_name" name="name" type="text" class="input" placeholder="TVL-HE"
                       value="{{ $errors->strand->any() ? old('name') : '' }}">
            </x-field>
            <x-field label="Category" name="category" for="strand_category" bag="strand">
                <select id="strand_category" name="category" class="input">
                    @foreach (TrackCategory::cases() as $category)
                        <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </x-field>
            <button type="submit" class="btn-gold w-full"><x-icon name="plus" class="size-4" /> Add strand</button>
            <p class="text-xs leading-relaxed text-slate-500">College programs (like HRS) are used for College sections. Academic, TechPro and TVL strands are used for SHS.</p>
        </form>
    </div>
</x-layouts.app>
