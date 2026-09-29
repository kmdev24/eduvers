{{--
    Inside of the bell dropdown. Rendered with the page, and re-fetched by app.js
    (GET notifications/dropdown) when a new notification arrives, so it stays live.
--}}
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
