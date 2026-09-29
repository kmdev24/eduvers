{{--
    One in-app notification (lesson / quiz / announcement).
    Clicking it marks it read and jumps to the item (notifications.open).
    <x-notification-item :notification="$n" />            — full row (notifications page)
    <x-notification-item :notification="$n" compact />    — short row (bell dropdown)
--}}
@props(['notification', 'compact' => false])

@php
    $data   = $notification->data;
    $unread = $notification->read_at === null;
@endphp

<a href="{{ route('notifications.open', $notification->id) }}"
   {{ $attributes->class([
        'group flex gap-3 transition hover:bg-ivory-100 focus-visible:bg-ivory-100 focus-visible:outline-none',
        'px-4 py-3' => $compact,
        'px-5 py-4 sm:px-6' => ! $compact,
        'bg-gold-50/60' => $unread,
   ]) }}>
    <span @class([
        'flex shrink-0 items-center justify-center rounded-xl ring-1',
        'size-9' => $compact,
        'size-11' => ! $compact,
        'bg-gold-100 text-gold-700 ring-gold-300' => $unread,
        'bg-ivory-200 text-slate-500 ring-ivory-300' => ! $unread,
    ])>
        <x-icon :name="$data['icon'] ?? 'sparkles'" :class="$compact ? 'size-4' : 'size-5'" />
    </span>

    <span class="min-w-0 flex-1">
        <span class="block text-[11px] font-semibold uppercase tracking-wide text-champagne-dark">{{ $data['headline'] ?? 'Update' }}</span>
        <span @class(['block text-sm text-ink-900', 'truncate' => $compact, 'font-semibold' => $unread, 'font-medium' => ! $unread])>{{ $data['title'] ?? '' }}</span>
        @if (! $compact && filled($data['excerpt'] ?? null))
            <span class="mt-0.5 line-clamp-2 block text-sm text-slate-500">{{ $data['excerpt'] }}</span>
        @endif
        <span class="mt-1 block text-xs text-slate-400">
            @if (filled($data['author'] ?? null)){{ $data['author'] }} · @endif
            <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->diffForHumans() }}</time>
        </span>
    </span>

    @if ($unread)
        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-gold-500 ring-2 ring-gold-100"><span class="sr-only">Unread</span></span>
    @endif
</a>
