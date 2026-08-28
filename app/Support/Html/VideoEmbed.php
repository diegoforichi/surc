<?php

namespace App\Support\Html;

class VideoEmbed
{
    /**
     * @var list<string>
     */
    protected const YOUTUBE_HOSTS = [
        'youtube.com',
        'm.youtube.com',
        'youtu.be',
        'youtube-nocookie.com',
    ];

    /**
     * @var list<string>
     */
    protected const VIMEO_HOSTS = [
        'vimeo.com',
        'player.vimeo.com',
    ];

    public static function embedSrc(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (preg_match('/^(javascript|data|vbscript):/i', $url) === 1) {
            return null;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (in_array($host, self::YOUTUBE_HOSTS, true)) {
            $id = self::youtubeId($host, $path, $parts['query'] ?? null);

            return $id !== null ? 'https://www.youtube-nocookie.com/embed/'.$id : null;
        }

        if (in_array($host, self::VIMEO_HOSTS, true)) {
            $id = self::vimeoId($host, $path);

            return $id !== null ? 'https://player.vimeo.com/video/'.$id : null;
        }

        return null;
    }

    protected static function youtubeId(string $host, string $path, ?string $query): ?string
    {
        $id = null;

        if ($host === 'youtu.be') {
            $id = explode('/', $path)[0] ?? null;
        } elseif (str_starts_with($path, 'embed/')) {
            $id = explode('/', substr($path, 6))[0] ?? null;
        } elseif (str_starts_with($path, 'shorts/')) {
            $id = explode('/', substr($path, 7))[0] ?? null;
        } else {
            parse_str((string) $query, $params);
            $id = isset($params['v']) ? (string) $params['v'] : null;
        }

        if ($id === null || $id === '' || preg_match('/^[A-Za-z0-9_-]{6,}$/', $id) !== 1) {
            return null;
        }

        return $id;
    }

    protected static function vimeoId(string $host, string $path): ?string
    {
        $segments = array_values(array_filter(explode('/', $path)));

        if ($host === 'player.vimeo.com') {
            $id = ($segments[0] ?? null) === 'video' ? ($segments[1] ?? null) : null;
        } else {
            $id = $segments[0] ?? null;
        }

        if ($id === null || preg_match('/^\d+$/', (string) $id) !== 1) {
            return null;
        }

        return (string) $id;
    }
}
