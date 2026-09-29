<x-layouts.app :title="$announcement->title">
    <a href="{{ route('student.dashboard') }}" class="text-sm font-medium text-gold-700 hover:text-gold-900">← Dashboard</a>

    <article class="card mt-4 max-w-3xl p-6 sm:p-10">
        <div class="flex flex-wrap items-center gap-2 text-xs">
            @if ($announcement->is_pinned)
                <span class="inline-flex items-center gap-1 font-semibold text-gold-700"><x-icon name="star" class="size-3.5" /> Pinned</span>
            @endif
            <span class="{{ $announcement->audience === \App\Enums\AnnouncementAudience::Section ? 'badge-slate' : 'badge-gold' }}">{{ $announcement->audienceLabel() }}</span>
            @if ($announcement->isExpired())
                <span class="badge-slate">Expired</span>
            @endif
        </div>

        <h1 class="heading-serif mt-4 text-3xl sm:text-4xl">{{ $announcement->title }}</h1>
        <p class="mt-3 text-sm text-slate-500">
            @if ($announcement->author) {{ $announcement->author->name }} · @endif
            {{ $announcement->created_at?->format('F j, Y g:i A') }}
        </p>

        <div class="gold-divider my-6"></div>

        <p class="text-[15px] leading-7 whitespace-pre-line text-slate-700">{{ $announcement->body }}</p>
    </article>
</x-layouts.app>
