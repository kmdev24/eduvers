<?php

namespace App\Http\Requests\Teacher;

use App\Models\Lesson;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
{
    /** Allowed attachment types and max size (KB). */
    public const MIMES  = 'pdf,doc,docx,ppt,pptx,xls,xlsx,txt,png,jpg,jpeg,zip';
    public const MAX_KB = 20480; // 20 MB

    /** Allowed uploaded-video types and max size (KB). */
    public const VIDEO_TYPES  = 'mp4,webm,mov';
    public const VIDEO_MAX_KB = 204800; // 200 MB

    /** Where the lesson's video comes from. */
    public const VIDEO_SOURCES = ['none', 'upload', 'link'];

    public function authorize(): bool
    {
        return true; // ownership is checked by LessonPolicy in the controller
    }

    public function rules(): array
    {
        /** @var Lesson|null $lesson */
        $lesson = $this->route('lesson');

        return [
            'title'             => ['required', 'string', 'max:255'],
            'subject_id'        => ['required', 'integer', Rule::in($this->user()->taughtSubjects()->pluck('id')->all())],
            'content'           => ['nullable', 'string', 'max:50000'],
            'attachment'        => ['nullable', 'file', 'mimes:'.self::MIMES, 'max:'.self::MAX_KB],
            'remove_attachment' => ['nullable', 'boolean'],

            'video_source' => ['nullable', Rule::in(self::VIDEO_SOURCES)],

            // Only checked when "Upload a video" is selected
            'video' => [
                'exclude_unless:video_source,upload',
                Rule::requiredIf(fn () => ! $lesson?->hasVideoFile()),
                'nullable', 'file',
                'mimes:'.self::VIDEO_TYPES,
                'extensions:'.self::VIDEO_TYPES,
                'max:'.self::VIDEO_MAX_KB,
            ],

            // Only checked when "Video link" is selected
            'video_url' => [
                'exclude_unless:video_source,link',
                'required', 'string', 'max:2048', 'url:http,https',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (Lesson::embedUrlFor((string) $value) === null) {
                        $fail('Use a YouTube, Vimeo or Google Drive video link.');
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'subject_id' => 'subject',
            'video'      => 'video file',
            'video_url'  => 'video link',
        ];
    }

    public function messages(): array
    {
        return [
            'subject_id.in'      => 'Choose one of the subjects assigned to you.',
            'attachment.max'     => 'The attachment may not be larger than 20 MB.',
            'attachment.mimes'   => 'Allowed file types: PDF, Word, PowerPoint, Excel, text, images or ZIP.',
            'video.required'     => 'Choose a video file to upload.',
            'video.mimes'        => 'Upload an MP4, WebM or MOV video.',
            'video.extensions'   => 'Upload an MP4, WebM or MOV video.',
            'video.max'          => 'The video may not be larger than 200 MB.',
            'video.uploaded'     => 'The video could not be uploaded. It may be larger than the server allows (upload_max_filesize in php.ini).',
            'video_url.required' => 'Paste a YouTube, Vimeo or Google Drive link.',
            'video_url.url'      => 'Enter a full link starting with https://',
        ];
    }
}
