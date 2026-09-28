{{-- Attachment card for a lesson. Expects $lesson. --}}
@if ($lesson->hasAttachment())
    <div class="card-gold p-6">
        <p class="text-xs font-semibold uppercase tracking-luxe text-champagne-dark">Attachment</p>
        <div class="mt-4 flex items-center gap-3">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-gold-gradient font-semibold text-ink-900 shadow-gold">
                {{ strtoupper(pathinfo($lesson->original_filename ?? $lesson->file_path, PATHINFO_EXTENSION)) ?: 'FILE' }}
            </span>
            <div class="min-w-0">
                <p class="truncate font-medium text-ink-900" title="{{ $lesson->original_filename }}">{{ $lesson->original_filename }}</p>
                <p class="text-xs text-slate-500">{{ $lesson->attachmentSize() }}</p>
            </div>
        </div>
        <div class="mt-5 grid gap-2 {{ $lesson->isPdf() ? 'grid-cols-2' : '' }}">
            @if ($lesson->isPdf())
                <a href="{{ route('lessons.attachment', ['lesson' => $lesson, 'inline' => 1]) }}" target="_blank" rel="noopener" class="btn-outline-gold">
                    <x-icon name="eye" class="size-4" /> Open
                </a>
            @endif
            <a href="{{ route('lessons.attachment', $lesson) }}" class="btn-gold">
                <x-icon name="arrow-right" class="size-4 rotate-90" /> Download
            </a>
        </div>
    </div>
@endif
