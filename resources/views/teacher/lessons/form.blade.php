@use('App\Http\Requests\Teacher\LessonRequest')
@php $editing = $lesson->exists; @endphp

<x-layouts.app :title="$editing ? 'Edit Lesson' : 'New Lesson'">
    <x-page-header eyebrow="Lessons" :title="$editing ? 'Edit lesson' : 'Write a new lesson'"
                   description="Students in every section that takes the subject can read this lesson.">
        <x-slot:actions>
            <a href="{{ $editing ? route('teacher.lessons.show', $lesson) : route('teacher.lessons.index') }}" class="btn-ghost">Cancel</a>
        </x-slot:actions>
    </x-page-header>

    @if ($subjects->isEmpty())
        <div class="card mt-8">
            <x-empty-state icon="stack" title="No subjects assigned yet" message="You need at least one assigned subject to write lessons." />
        </div>
    @else
        <form method="POST" enctype="multipart/form-data" data-upload-form
              action="{{ $editing ? route('teacher.lessons.update', $lesson) : route('teacher.lessons.store') }}"
              class="mt-8 grid gap-6 xl:grid-cols-3">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="space-y-6 xl:col-span-2">
            <div class="card space-y-6 p-6 sm:p-8">
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-field label="Lesson title" name="title" class="sm:col-span-2">
                        <input id="title" name="title" type="text" value="{{ old('title', $lesson->title) }}" required class="input" placeholder="Introduction to Food Safety">
                    </x-field>
                    <x-field label="Subject" name="subject_id">
                        <select id="subject_id" name="subject_id" required class="input">
                            <option value="">Select…</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected((string) old('subject_id', $lesson->subject_id) === (string) $subject->id)>
                                    {{ $subject->code }} · {{ $subject->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>
                </div>

                <x-field label="Content" name="content" hint="Supports Markdown: # Heading, **bold**, *italic*, - bullet lists, 1. numbered lists, > quotes and [links](https://…).">
                    <textarea id="content" name="content" rows="18" class="input font-mono text-[13px] leading-6"
                              placeholder="# Learning objectives&#10;&#10;By the end of this lesson you will be able to…">{{ old('content', $lesson->content) }}</textarea>
                </x-field>
            </div>

            {{-- ================= Video ================= --}}
            @php
                $videoSource = old('video_source', $lesson->hasVideoFile() ? 'upload' : (filled($lesson->video_url) ? 'link' : 'none'));
                $videoOptions = [
                    'none'   => ['icon' => 'x',            'label' => 'No video',       'hint' => 'Text and files only'],
                    'upload' => ['icon' => 'video-camera', 'label' => 'Upload a video', 'hint' => 'MP4, WebM or MOV'],
                    'link'   => ['icon' => 'link',         'label' => 'Video link',     'hint' => 'YouTube, Vimeo, Drive'],
                ];
            @endphp
            <div class="card space-y-5 p-6 sm:p-8" data-video-form>
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gold-50 text-gold-600 ring-1 ring-gold-200">
                        <x-icon name="video-camera" class="size-5" />
                    </span>
                    <div>
                        <h2 class="font-serif text-lg font-semibold">Video lesson</h2>
                        <p class="text-sm text-slate-500">Optional. Students see the video at the top of the lesson, above the content and files.</p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Video source">
                    @foreach ($videoOptions as $value => $option)
                        <label class="relative cursor-pointer">
                            <input type="radio" name="video_source" value="{{ $value }}" class="peer sr-only" @checked($videoSource === $value)>
                            <span class="flex h-full items-center gap-3 rounded-xl border border-ivory-300 bg-white p-3.5 transition hover:border-gold-300
                                         peer-checked:border-gold-500 peer-checked:bg-gold-50/50 peer-checked:ring-2 peer-checked:ring-gold-500/20
                                         peer-focus-visible:ring-2 peer-focus-visible:ring-gold-500/40">
                                <x-icon :name="$option['icon']" class="size-5 shrink-0 text-gold-600" />
                                <span>
                                    <span class="block text-sm font-medium text-ink-900">{{ $option['label'] }}</span>
                                    <span class="block text-xs text-slate-500">{{ $option['hint'] }}</span>
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>

                {{-- Upload --}}
                <div data-video-panel="upload" @class(['space-y-4', 'hidden' => $videoSource !== 'upload'])>
                    @if ($lesson->hasVideoFile())
                        <div class="overflow-hidden rounded-xl bg-ink-950 ring-1 ring-gold-500/20">
                            <video controls preload="metadata" playsinline class="aspect-video w-full bg-black">
                                <source src="{{ route('lessons.video', $lesson) }}" type="{{ $lesson->videoMimeType() }}">
                            </video>
                            <p class="bg-ink-900 px-4 py-2 text-xs text-slate-400">Current video. Choose a new file below to replace it.</p>
                        </div>
                    @endif
                    <x-field :label="$lesson->hasVideoFile() ? 'Replace video file' : 'Video file'" name="video"
                             hint="MP4 (recommended) or WebM, up to 200 MB. MOV files upload fine but may only play in Safari.">
                        <input id="video" name="video" type="file"
                               accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov"
                               data-max-bytes="{{ LessonRequest::VIDEO_MAX_KB * 1024 }}"
                               class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-ink-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-ivory hover:file:bg-ink-800">
                    </x-field>
                </div>

                {{-- Link --}}
                <div data-video-panel="link" @class(['space-y-4', 'hidden' => $videoSource !== 'link'])>
                    <x-field label="Video link" name="video_url"
                             hint="Paste a YouTube, Vimeo or Google Drive link. For Google Drive, set sharing to “Anyone with the link”.">
                        <div class="relative">
                            <x-icon name="link" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                            <input id="video_url" name="video_url" type="url" maxlength="2048" class="input pl-10"
                                   value="{{ old('video_url', $lesson->video_url) }}"
                                   placeholder="https://www.youtube.com/watch?v=…">
                        </div>
                    </x-field>
                    @if ($lesson->videoEmbedUrl())
                        <div class="overflow-hidden rounded-xl bg-black ring-1 ring-gold-500/20">
                            <div class="aspect-video">
                                <iframe src="{{ $lesson->videoEmbedUrl() }}" title="Current video" class="size-full" loading="lazy"
                                        allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- None --}}
                <div data-video-panel="none" @class(['hidden' => $videoSource !== 'none'])>
                    <p class="rounded-xl border border-dashed border-ivory-300 px-4 py-3 text-sm text-slate-500">
                        @if ($lesson->hasVideo())
                            Saving with “No video” will <strong class="text-red-600">remove the current video</strong> from this lesson.
                        @else
                            This lesson won't have a video.
                        @endif
                    </p>
                </div>
            </div>
            </div>

            <div class="space-y-6">
                <div class="card space-y-4 p-6">
                    <div>
                        <h2 class="font-serif text-lg font-semibold">Attachment</h2>
                        <p class="text-sm text-slate-500">Optional. One file, up to 20 MB.</p>
                    </div>

                    @if ($lesson->hasAttachment())
                        <div class="flex items-center gap-3 rounded-xl border border-gold-200 bg-gold-50/60 p-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-gold-600 ring-1 ring-gold-200">
                                <x-icon name="clipboard" class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('lessons.attachment', $lesson) }}" class="block truncate text-sm font-medium text-ink-900 hover:text-gold-700">{{ $lesson->original_filename }}</a>
                                <p class="text-xs text-slate-500">{{ $lesson->attachmentSize() }}</p>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_attachment" value="1" class="rounded border-ivory-300 text-red-600 focus:ring-red-500/30">
                            Remove this file
                        </label>
                    @endif

                    <x-field :label="$lesson->hasAttachment() ? 'Replace with a new file' : 'Upload a file'" name="attachment"
                             hint="PDF, Word, PowerPoint, Excel, text, images or ZIP.">
                        <input id="attachment" name="attachment" type="file" accept=".{{ str_replace(',', ',.', LessonRequest::MIMES) }}"
                               class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-ink-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-ivory hover:file:bg-ink-800">
                    </x-field>
                </div>

                <div class="card-gold p-6">
                    <button type="submit" class="btn-gold w-full py-3" data-uploading-text="Uploading… please wait">{{ $editing ? 'Save lesson' : 'Publish lesson' }}</button>
                    <p class="mt-3 text-center text-xs text-slate-500">Large videos can take a few minutes to upload. Keep this page open.</p>
                </div>
            </div>
        </form>
    @endif
</x-layouts.app>
