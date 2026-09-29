@props(['eyebrow' => null, 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 class="heading-serif mt-2 text-3xl sm:text-4xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-2 max-w-2xl text-slate-500">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-3">{{ $actions }}</div>
    @endisset
</div>
