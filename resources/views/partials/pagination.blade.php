{{-- Gold pagination. Usage: {{ $items->links('partials.pagination') }} --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-col items-center justify-between gap-4 sm:flex-row">
        <p class="text-sm text-slate-500">
            Showing <span class="font-medium text-ink-800">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-ink-800">{{ $paginator->lastItem() }}</span>
            of <span class="font-medium text-ink-800">{{ $paginator->total() }}</span>
        </p>

        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg px-3 py-2 text-sm text-slate-300">‹ Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-lg px-3 py-2 text-sm text-slate-600 transition hover:bg-ivory-200 hover:text-ink-900">‹ Prev</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-sm text-slate-400">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="flex size-9 items-center justify-center rounded-lg bg-gold-gradient text-sm font-semibold text-ink-900 shadow-gold">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="flex size-9 items-center justify-center rounded-lg text-sm text-slate-600 transition hover:bg-ivory-200 hover:text-ink-900">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-lg px-3 py-2 text-sm text-slate-600 transition hover:bg-ivory-200 hover:text-ink-900">Next ›</a>
            @else
                <span class="rounded-lg px-3 py-2 text-sm text-slate-300">Next ›</span>
            @endif
        </div>
    </nav>
@endif
