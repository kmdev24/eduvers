<x-layouts.app title="Notifications">
    <x-page-header eyebrow="Inbox" title="Notifications"
                   description="New lessons, quizzes and announcements for you. Click one to open it.">
        @if ($unreadCount > 0)
            <x-slot:actions>
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn-outline-gold"><x-icon name="check" class="size-4" /> Mark all as read</button>
                </form>
            </x-slot:actions>
        @endif
    </x-page-header>

    {{-- All / Unread --}}
    <nav class="mt-8 inline-flex rounded-xl border border-ivory-300 bg-white p-1 text-sm shadow-soft" aria-label="Filter notifications">
        @foreach (['all' => 'All', 'unread' => 'Unread'] as $key => $label)
            <a href="{{ route('notifications.index', $key === 'all' ? [] : ['filter' => $key]) }}"
               @class([
                   'rounded-lg px-4 py-1.5 font-medium transition',
                   'bg-ink-900 text-ivory' => $filter === $key,
                   'text-slate-600 hover:text-ink-900' => $filter !== $key,
               ])
               @if ($filter === $key) aria-current="page" @endif>
                {{ $label }}
                @if ($key === 'unread' && $unreadCount > 0)
                    <span class="ml-1 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $unreadCount }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <section class="card mt-4 overflow-hidden">
        @if ($notifications->isEmpty())
            <x-empty-state icon="bell"
                           :title="$filter === 'unread' ? 'No unread notifications' : 'No notifications yet'"
                           message="When your teachers post a lesson, quiz or announcement, you’ll see it here and get an email." />
        @else
            <ul class="divide-y divide-ivory-200">
                @foreach ($notifications as $notification)
                    <li class="flex items-stretch">
                        <x-notification-item :notification="$notification" class="min-w-0 flex-1" />

                        @if ($notification->read_at === null)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="flex items-center pr-3 sm:pr-5">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="icon-btn" title="Mark as read" aria-label="Mark “{{ $notification->data['title'] ?? 'notification' }}” as read">
                                    <x-icon name="check" class="size-4" />
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($notifications->hasPages())
                <div class="border-t border-ivory-300 px-6 py-4">
                    {{ $notifications->links('partials.pagination') }}
                </div>
            @endif
        @endif
    </section>
</x-layouts.app>
