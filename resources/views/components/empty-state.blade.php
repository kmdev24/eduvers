@props(['icon' => 'stack', 'title', 'message' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-ivory-200 text-champagne-dark ring-1 ring-ivory-300">
        <x-icon :name="$icon" class="size-6" />
    </span>
    <p class="mt-4 font-medium text-ink-900">{{ $title }}</p>
    @if ($message)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $message }}</p>
    @endif
</div>
