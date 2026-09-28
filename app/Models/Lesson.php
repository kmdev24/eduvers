<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class Lesson extends Model
{
    use HasFactory;

    /** Private disk (storage/app/private). Files are served via an authorized route. */
    public const DISK = 'local';

    protected $fillable = [
        'title',
        'content',
        'file_path',
        'original_filename',
        'file_size',
        'video_url',
        'video_path',
        'subject_id',
        'academic_term_id',
        'teacher_id',
    ];

    protected static function booted(): void
    {
        // Remove stored files (attachment + uploaded video) when a lesson is deleted
        static::deleting(function (Lesson $lesson) {
            $lesson->deleteAttachment();
            $lesson->deleteVideoFile();
        });
    }

    public function subject(): BelongsTo      { return $this->belongsTo(Subject::class); }
    public function academicTerm(): BelongsTo { return $this->belongsTo(AcademicTerm::class); }
    public function teacher(): BelongsTo      { return $this->belongsTo(User::class, 'teacher_id'); }

    /* Attachment ------------------------------------------------------- */

    public function hasAttachment(): bool
    {
        return filled($this->file_path);
    }

    public function isPdf(): bool
    {
        return $this->hasAttachment()
            && Str::lower(pathinfo($this->original_filename ?? $this->file_path, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function attachmentSize(): ?string
    {
        return $this->file_size ? Number::fileSize($this->file_size) : null;
    }

    public function deleteAttachment(): void
    {
        if ($this->hasAttachment()) {
            Storage::disk(self::DISK)->delete($this->file_path);
        }

        $this->file_path = null;
        $this->original_filename = null;
        $this->file_size = null;
    }

    /* Video ------------------------------------------------------------ */

    public function hasVideo(): bool
    {
        return $this->hasVideoFile() || $this->videoEmbedUrl() !== null;
    }

    public function hasVideoFile(): bool
    {
        return filled($this->video_path);
    }

    public function videoMimeType(): string
    {
        return match (Str::lower(pathinfo((string) $this->video_path, PATHINFO_EXTENSION))) {
            'webm'  => 'video/webm',
            'mov'   => 'video/quicktime',
            default => 'video/mp4',
        };
    }

    public function deleteVideoFile(): void
    {
        if ($this->hasVideoFile()) {
            Storage::disk(self::DISK)->delete($this->video_path);
        }

        $this->video_path = null;
    }

    /** Safe iframe URL for the stored video link, or null if unsupported. */
    public function videoEmbedUrl(): ?string
    {
        return filled($this->video_url) ? self::embedUrlFor($this->video_url) : null;
    }

    /** "YouTube", "Vimeo" or "Google Drive". */
    public function videoProvider(): ?string
    {
        $embed = $this->videoEmbedUrl();

        return match (true) {
            $embed === null                          => null,
            str_contains($embed, 'youtube')          => 'YouTube',
            str_contains($embed, 'vimeo')            => 'Vimeo',
            str_contains($embed, 'drive.google.com') => 'Google Drive',
            default                                  => null,
        };
    }

    /**
     * Turn a YouTube / Vimeo / Google Drive link into an embeddable player URL.
     * The URL is rebuilt from the extracted video ID, so arbitrary pages can
     * never be loaded into the lesson's iframe. Returns null when unsupported.
     */
    public static function embedUrlFor(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower(preg_replace('/^(www\.|m\.)/', '', $parts['host'] ?? ''));
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        // YouTube: watch?v=ID, youtu.be/ID, /embed/ID, /shorts/ID, /live/ID
        $youtubeId = null;
        if ($host === 'youtu.be') {
            $youtubeId = explode('/', trim($path, '/'))[0] ?? null;
        } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com', 'music.youtube.com'], true)) {
            if (! empty($query['v']) && is_string($query['v'])) {
                $youtubeId = $query['v'];
            } elseif (preg_match('#^/(?:embed|shorts|live|v)/([^/?]+)#', $path, $m)) {
                $youtubeId = $m[1];
            }
        }
        if ($youtubeId !== null) {
            if (! preg_match('/^[A-Za-z0-9_-]{6,20}$/', $youtubeId)) {
                return null;
            }
            $start = isset($query['t']) && is_string($query['t']) ? (int) rtrim($query['t'], 's')
                   : (isset($query['start']) && is_string($query['start']) ? (int) $query['start'] : 0);

            return 'https://www.youtube-nocookie.com/embed/'.$youtubeId.($start > 0 ? '?start='.$start : '');
        }

        // Vimeo: vimeo.com/ID, vimeo.com/ID/HASH, vimeo.com/channels/x/ID, player.vimeo.com/video/ID?h=HASH
        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)
            && preg_match('#/(\d{6,12})(?:/([a-f0-9]{6,20}))?(?:/|$)#', $path, $m)) {
            $hash = $m[2] ?? (isset($query['h']) && is_string($query['h']) && preg_match('/^[a-f0-9]{6,20}$/', $query['h']) ? $query['h'] : null);

            return 'https://player.vimeo.com/video/'.$m[1].($hash ? '?h='.$hash : '');
        }

        // Google Drive: /file/d/ID/view, open?id=ID, uc?id=ID
        if ($host === 'drive.google.com') {
            $driveId = preg_match('#/file/d/([A-Za-z0-9_-]{10,})#', $path, $m) ? $m[1]
                     : (isset($query['id']) && is_string($query['id']) && preg_match('/^[A-Za-z0-9_-]{10,}$/', $query['id']) ? $query['id'] : null);

            return $driveId ? 'https://drive.google.com/file/d/'.$driveId.'/preview' : null;
        }

        return null;
    }

    /* Content ---------------------------------------------------------- */

    /** Lesson body written in Markdown, rendered safely (raw HTML stripped). */
    public function renderedContent(): HtmlString
    {
        return new HtmlString(Str::markdown((string) $this->content, [
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }

    public function excerpt(int $limit = 140): string
    {
        $plain = preg_replace('/[#>*_`\[\]\(\)-]+/', ' ', strip_tags((string) $this->content));

        return Str::limit(trim(preg_replace('/\s+/', ' ', $plain)), $limit);
    }
}
