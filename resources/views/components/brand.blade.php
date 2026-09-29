{{-- Movers Institute logo + EduVers wordmark. Use dark=true on dark backgrounds. --}}
@props(['dark' => false])

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <x-logo class="size-11" />
    <span class="leading-tight">
        <span @class(['block font-serif text-xl font-semibold tracking-wide', 'text-ivory' => $dark, 'text-ink-900' => ! $dark])>EduVers</span>
        <span @class(['block text-[10px] font-semibold uppercase tracking-luxe', 'text-gold-400' => $dark, 'text-champagne-dark' => ! $dark])>Movers Institute</span>
    </span>
</div>
