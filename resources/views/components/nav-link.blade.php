@props(['icon', 'route' => null, 'match' => null])

@php
    $active = $route && request()->routeIs($match ?? $route);
@endphp

@if ($route)
    <a href="{{ route($route) }}"
       @if ($active) aria-current="page" @endif
       @class([
           'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
           'bg-white/[0.07] text-gold-300' => $active,
           'text-slate-300 hover:bg-white/[0.04] hover:text-white' => ! $active,
       ])>
        @if ($active)
            <span class="absolute inset-y-2 -left-3 w-1 rounded-r-full bg-gold-gradient"></span>
        @endif
        <x-icon :name="$icon" :class="$active ? 'size-5 text-gold-400' : 'size-5 text-slate-400 group-hover:text-gold-300'" />
        <span>{{ $slot }}</span>
    </a>
@else
    {{-- Module not built yet --}}
    <span class="flex cursor-default items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-500">
        <x-icon :name="$icon" class="size-5 text-slate-600" />
        <span>{{ $slot }}</span>
        <span class="ml-auto rounded-full border border-slate-700 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Soon</span>
    </span>
@endif
