{{--
    Header bell with unread badge and a dropdown of recent notifications.
    Dropdown + badge polling live in resources/js/app.js ([data-dropdown], [data-notification-bell]).
--}}
@props(['user'])

@php
    $unread = $user->unreadNotifications()->count();
    $recent = $user->notifications()->take(6)->get();
@endphp

<div class="relative" data-dropdown data-notification-bell data-count-url="{{ route('notifications.count') }}" data-poll-seconds="60">
    <button type="button" data-dropdown-toggle aria-expanded="false" aria-controls="notification-panel"
            class="relative flex size-10 items-center justify-center rounded-full border border-gold-300/70 bg-white text-ink-800 shadow-soft transition hover:border-gold-400 hover:text-gold-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-500">
        <x-icon name="bell" class="size-5" />
        <span class="sr-only">Notifications</span>
        <span data-notification-badge aria-live="polite"
              @class(['absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-none font-bold text-white ring-2 ring-ivory', 'hidden' => $unread === 0])>{{ $unread > 9 ? '9+' : $unread }}</span>
    </button>

    <div id="notification-panel" data-dropdown-panel
         class="card fixed inset-x-4 top-20 z-50 hidden overflow-hidden sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 sm:mt-3 sm:w-96">
        <div class="flex items-center justify-between gap-3 border-b border-ivory-300 px-4 py-3">
            <div>
                <p class="font-serif text-base font-semibold text-ink-900">Notifications</p>
                <p class="text-xs text-slate-500">{{ $unread > 0 ? $unread.' unread' : 'You’re all caught up' }}</p>
            </div>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-gold-700 hover:bg-gold-50 hover:text-gold-900">
                        <x-icon name="check" class="size-3.5" /> Mark all read
                    </button>
                </form>
            @endif
        </div>

        @if ($recent->isEmpty())
            <x-empty-state icon="bell" title="No notifications yet" message="New lessons, quizzes and announcements will show up here." class="py-10!" />
        @else
            <div class="max-h-[60vh] divide-y divide-ivory-200 overflow-y-auto">
                @foreach ($recent as $notification)
                    <x-notification-item :notification="$notification" compact />
                @endforeach
            </div>
        @endif

        <a href="{{ route('notifications.index') }}" class="block border-t border-ivory-300 bg-ivory-100 px-4 py-3 text-center text-sm font-medium text-gold-700 hover:bg-ivory-200 hover:text-gold-900">
            View all notifications →
        </a>
    </div>
</div>
