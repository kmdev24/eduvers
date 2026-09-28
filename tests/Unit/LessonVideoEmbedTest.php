<?php

namespace Tests\Unit;

use App\Models\Lesson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Video links are converted to safe embed URLs; anything else is rejected.
 */
class LessonVideoEmbedTest extends TestCase
{
    public static function supportedLinks(): array
    {
        return [
            'youtube watch'      => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'youtube short link' => ['https://youtu.be/dQw4w9WgXcQ?t=90', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=90'],
            'youtube shorts'     => ['https://youtube.com/shorts/abcDEF12345', 'https://www.youtube-nocookie.com/embed/abcDEF12345'],
            'youtube embed'      => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'vimeo'              => ['https://vimeo.com/123456789', 'https://player.vimeo.com/video/123456789'],
            'vimeo unlisted'     => ['https://vimeo.com/123456789/abcdef1234', 'https://player.vimeo.com/video/123456789?h=abcdef1234'],
            'google drive'       => ['https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view?usp=sharing', 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/preview'],
            'google drive open'  => ['https://drive.google.com/open?id=1AbCdEfGhIjKlMnOpQrStUv', 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/preview'],
        ];
    }

    #[DataProvider('supportedLinks')]
    public function test_supported_links_become_embed_urls(string $link, string $expected): void
    {
        $this->assertSame($expected, Lesson::embedUrlFor($link));
    }

    public static function rejectedLinks(): array
    {
        return [
            'other site'        => ['https://evil.example.com/watch?v=dQw4w9WgXcQ'],
            'javascript scheme' => ['javascript:alert(1)'],
            'bad youtube id'    => ['https://www.youtube.com/watch?v=<script>'],
            'plain text'        => ['not a url'],
        ];
    }

    #[DataProvider('rejectedLinks')]
    public function test_unsupported_or_unsafe_links_are_rejected(string $link): void
    {
        $this->assertNull(Lesson::embedUrlFor($link));
    }
}
