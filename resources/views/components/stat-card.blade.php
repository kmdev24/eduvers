@props(['label', 'value', 'icon' => 'chart', 'hint' => null])

<div {{ $attributes->merge(['class' => 'card group relative overflow-hidden p-6 transition duration-300 hover:-translate-y-0.5 hover:shadow-elevated']) }}>
    {{-- gold hairline that lights up on hover --}}
    <div class="absolute inset-x-0 top-0 h-0.5 bg-gold-gradient opacity-0 transition duration-300 group-hover:opacity-100"></div>

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
        <p class="mt-4 text-sm text-slate-500">{{ $hint }}</p>
    @endif
</div>
