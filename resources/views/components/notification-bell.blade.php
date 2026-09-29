{{--
    Header bell with unread badge and a dropdown of recent notifications.
    app.js checks for new notifications every few seconds ([data-notification-bell]):
    it updates the badge, refreshes the dropdown and shows a toast — no page reload needed.
--}}
@props(['user'])

@php
    $unread = $user->unreadNotifications()->count();
    $recent = $user->notifications()->take(6)->get();
@endphp

<div class="relative" data-dropdown data-notification-bell
     data-count-url="{{ route('notifications.count') }}"
     data-dropdown-url="{{ route('notifications.dropdown') }}"
     data-latest-id="{{ $recent->first()?->id }}"
     data-poll-seconds="15">
    <button type="button" data-dropdown-toggle aria-expanded="false" aria-controls="notification-panel"
            class="relative flex size-10 items-center justify-center rounded-full border border-gold-300/70 bg-white text-ink-800 shadow-soft transition hover:border-gold-400 hover:text-gold-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-500">
        <x-icon name="bell" class="size-5" />
        <span class="sr-only">Notifications</span>
        <span data-notification-badge aria-live="polite"
              @class(['absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-none font-bold text-white ring-2 ring-ivory', 'hidden' => $unread === 0])>{{ $unread > 9 ? '9+' : $unread }}</span>
    </button>

    <div id="notification-panel" data-dropdown-panel data-notification-list
         class="card fixed inset-x-4 top-20 z-50 hidden overflow-hidden sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 sm:mt-3 sm:w-96">
        @include('notifications._dropdown', ['unread' => $unread, 'recent' => $recent])
    </div>
</div>

{{-- Pop-up for newly arrived notifications (filled by app.js) --}}
<div data-notification-toasts class="pointer-events-none fixed inset-x-4 bottom-4 z-[60] flex flex-col items-end gap-3 sm:inset-x-auto sm:right-6 sm:bottom-6" aria-live="polite"></div>
