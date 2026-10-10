@props(['label', 'value', 'icon' => 'chart', 'hint' => null, 'href' => null])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card group relative block overflow-hidden p-6 transition duration-300 hover:-translate-y-0.5 hover:shadow-elevated'.($href ? ' outline-none focus-visible:ring-2 focus-visible:ring-gold-500/60' : '')]) }}>
    {{-- gold hairline that lights up on hover --}}
    <div class="absolute inset-x-0 top-0 h-0.5 bg-gold-gradient opacity-0 transition duration-300 group-hover:opacity-100 group-focus-visible:opacity-100"></div>

    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-luxe text-slate-500">{{ $label }}</p>
            <p class="mt-3 font-serif text-4xl font-semibold tabular-nums text-ink-900">{{ $value }}</p>
        </div>
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gold-50 text-gold-600 ring-1 ring-gold-200">
            <x-icon :name="$icon" class="size-5" />
        </span>
    </div>

    @if ($hint)
        <p class="mt-4 flex items-center gap-1.5 text-sm text-slate-500">
            {{ $hint }}
            @if ($href)
                <x-icon name="arrow-right" class="ml-auto size-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-gold-600" />
            @endif
        </p>
    @endif
</{{ $tag }}>
