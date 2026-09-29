{{-- Video player for a lesson: HTML5 player for uploaded files, iframe for YouTube / Vimeo / Google Drive. Expects $lesson. --}}
@if ($lesson->hasVideoFile())
    <figure class="overflow-hidden rounded-2xl bg-ink-950 shadow-elevated ring-1 ring-gold-500/25">
        <video controls preload="metadata" playsinline controlslist="nodownload"
               class="aspect-video w-full bg-black" title="Video: {{ $lesson->title }}">
            <source src="{{ route('lessons.video', $lesson) }}" type="{{ $lesson->videoMimeType() }}">
            <p class="p-6 text-sm text-slate-300">Your browser can't play this video format. Try another browser such as Chrome, Edge or Safari.</p>
        </video>
        <figcaption class="flex items-center gap-2 bg-ink-900 px-5 py-3 text-xs text-slate-400">
            <x-icon name="video-camera" class="size-4 text-gold-400" />
            Video lesson
            @if ($lesson->videoMimeType() === 'video/quicktime')
                <span class="ml-auto text-slate-500">MOV videos play best in Safari. If it doesn't play, ask your teacher for an MP4.</span>
            @endif
        </figcaption>
    </figure>
@elseif ($embedUrl = $lesson->videoEmbedUrl())
    <figure class="overflow-hidden rounded-2xl bg-ink-950 shadow-elevated ring-1 ring-gold-500/25">
        <div class="aspect-video w-full bg-black">
            <iframe src="{{ $embedUrl }}" title="Video: {{ $lesson->title }}" class="size-full" loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen"
                    allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
        <figcaption class="flex items-center gap-2 bg-ink-900 px-5 py-3 text-xs text-slate-400">
            <x-icon name="video-camera" class="size-4 text-gold-400" />
            Video from {{ $lesson->videoProvider() }}
            <a href="{{ $lesson->video_url }}" target="_blank" rel="noopener noreferrer" class="ml-auto font-medium text-gold-400 hover:text-gold-300">Open in new tab ↗</a>
        </figcaption>
    </figure>
@endif
