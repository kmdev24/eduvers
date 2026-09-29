@use('App\Enums\AnnouncementAudience')

<x-layouts.app title="Announcements">
    <x-page-header eyebrow="Communication" title="Announcements"
                   :description="auth()->user()->isDeveloper()
                        ? 'Broadcast school-wide notices, or target all teachers, all students or a single section.'
                        : 'Post notices to the sections you teach. They appear on your students\' dashboards.'" />

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        {{-- List --}}
        <div class="space-y-4 xl:col-span-2">
            @forelse ($announcements as $item)
                <article @class(['card p-6', 'ring-2 ring-gold-300/60' => $item->is_pinned, 'opacity-60' => $item->isExpired()])>
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                @if ($item->is_pinned)
                                    <span class="inline-flex items-center gap-1 font-semibold text-gold-700"><x-icon name="star" class="size-3.5" /> Pinned</span>
                                @endif
                                <span class="{{ $item->audience === AnnouncementAudience::Section ? 'badge-slate' : 'badge-gold' }}">{{ $item->audienceLabel() }}</span>
                                @if ($item->isExpired())
                                    <span class="badge-slate">Expired</span>
                                @elseif ($item->expires_at)
                                    <span class="text-slate-400">Until {{ $item->expires_at->format('M j, g:i A') }}</span>
                                @endif
                            </div>
                            <h2 class="mt-2 font-serif text-xl font-semibold text-ink-900">{{ $item->title }}</h2>
                            <p class="mt-1 text-xs text-slate-400">{{ $item->author?->name }} · {{ $item->created_at?->format('M j, Y g:i A') }}</p>
                        </div>
                        @can('update', $item)
                            <div class="flex shrink-0 items-center gap-1">
                                <a href="{{ route('announcements.edit', $item) }}" class="icon-btn" title="Edit" aria-label="Edit {{ $item->title }}"><x-icon name="pencil" class="size-4" /></a>
                                <form method="POST" action="{{ route('announcements.destroy', $item) }}" data-confirm="Delete this announcement?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn-danger" title="Delete" aria-label="Delete {{ $item->title }}"><x-icon name="trash" class="size-4" /></button>
                                </form>
                            </div>
                        @endcan
                    </div>
                    <p class="mt-4 text-sm leading-relaxed whitespace-pre-line text-slate-600">{{ $item->body }}</p>
                </article>
            @empty
                <div class="card"><x-empty-state icon="megaphone" title="No announcements yet" message="Use the form to post your first announcement." /></div>
            @endforelse

            <div>{{ $announcements->links('partials.pagination') }}</div>
        </div>

        {{-- New announcement --}}
        <form method="POST" action="{{ route('announcements.store') }}" class="card-gold h-fit space-y-5 p-6">
            @csrf
            <div>
                <h2 class="font-serif text-lg font-semibold">New announcement</h2>
                <p class="text-sm text-slate-500">It appears on dashboards right away.</p>
            </div>
            @include('announcements._form')
            <button type="submit" class="btn-gold w-full"><x-icon name="megaphone" class="size-4" /> Post announcement</button>
        </form>
    </div>
</x-layouts.app>
