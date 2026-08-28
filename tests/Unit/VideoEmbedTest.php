<?php

namespace Tests\Unit;

use App\Support\Html\VideoEmbed;
use PHPUnit\Framework\TestCase;

class VideoEmbedTest extends TestCase
{
    public function test_it_accepts_youtube_and_vimeo_urls(): void
    {
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            VideoEmbed::embedSrc('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            VideoEmbed::embedSrc('https://youtu.be/dQw4w9WgXcQ'),
        );
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            VideoEmbed::embedSrc('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'),
        );
        $this->assertSame(
            'https://player.vimeo.com/video/123456789',
            VideoEmbed::embedSrc('https://vimeo.com/123456789'),
        );
        $this->assertSame(
            'https://player.vimeo.com/video/123456789',
            VideoEmbed::embedSrc('https://player.vimeo.com/video/123456789'),
        );
    }

    public function test_it_rejects_invalid_urls(): void
    {
        $this->assertNull(VideoEmbed::embedSrc(null));
        $this->assertNull(VideoEmbed::embedSrc(''));
        $this->assertNull(VideoEmbed::embedSrc('javascript:alert(1)'));
        $this->assertNull(VideoEmbed::embedSrc('https://example.com/video.mp4'));
        $this->assertNull(VideoEmbed::embedSrc('https://youtube.com/watch?v='));
    }
}
