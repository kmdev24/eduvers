{{-- Announcements feed for dashboards. Expects a collection of Announcement models. --}}
@props(['announcements'])

@use('App\Enums\AnnouncementAudience')

<section {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    <div class="flex items-center justify-between border-b border-ivory-300 px-6 py-5">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-gold-50 text-gold-600 ring-1 ring-gold-200">
                <x-icon name="megaphone" class="size-5" />
            </span>
            <div>
                <h2 class="font-serif text-lg font-semibold">Announcements</h2>
                <p class="text-sm text-slate-500">From the school and your teachers</p>
            </div>
        </div>
        @if (auth()->user()->isDeveloper() || auth()->user()->isTeacher())
            <a href="{{ route('announcements.index') }}" class="text-sm font-medium text-gold-700 hover:text-gold-900">Manage →</a>
        @endif
    </div>

    @if ($announcements->isEmpty())
        <p class="px-6 py-8 text-center text-sm text-slate-400">No announcements right now.</p>
    @else
        <ul class="divide-y divide-ivory-200">
            @foreach ($announcements as $announcement)
                <li id="announcement-{{ $announcement->id }}" @class(['px-6 py-5', 'bg-gold-50/40' => $announcement->is_pinned])>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        @if ($announcement->is_pinned)
                            <span class="inline-flex items-center gap-1 font-semibold text-gold-700"><x-icon name="star" class="size-3.5" /> Pinned</span>
                        @endif
                        <span class="{{ $announcement->audience === AnnouncementAudience::Section ? 'badge-slate' : 'badge-gold' }}">{{ $announcement->audienceLabel() }}</span>
                        <span class="text-slate-400">{{ $announcement->author?->name }} · {{ $announcement->created_at?->diffForHumans() }}</span>
                    </div>
                    <h3 class="mt-2 font-medium text-ink-900">
                        @if (auth()->user()->isStudent())
                            <a href="{{ route('student.announcements.show', $announcement) }}" class="hover:text-gold-700">{{ $announcement->title }}</a>
                        @else
                            {{ $announcement->title }}
                        @endif
                    </h3>
                    @if (mb_strlen($announcement->body) > 280)
                        <details class="group mt-1">
                            <summary class="cursor-pointer list-none text-sm text-slate-600">
                                <span class="group-open:hidden">{{ \Illuminate\Support\Str::limit($announcement->body, 280) }} <span class="font-medium text-gold-700">Read more</span></span>
                            </summary>
                            <p class="text-sm leading-relaxed whitespace-pre-line text-slate-600">{{ $announcement->body }}</p>
                        </details>
                    @else
                        <p class="mt-1 text-sm leading-relaxed whitespace-pre-line text-slate-600">{{ $announcement->body }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
