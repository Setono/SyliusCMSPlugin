<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Video;

/**
 * A YouTube or Vimeo video as the Video block embeds it, read from the URL an editor pastes: a watch page, a share link,
 * an embed URL and the like. YouTube and Vimeo videos aren't media items, the Video block holds their URL. The embed
 * is the providers' privacy-enhanced one: YouTube's youtube-nocookie.com, and Vimeo's player without tracking.
 * See "Video" in docs/spec/blocks.md
 */
final readonly class VideoEmbed
{
    public const YOUTUBE = 'youtube';

    public const VIMEO = 'vimeo';

    private const YOUTUBE_ID = '[A-Za-z0-9_-]{11}';

    /**
     * @param self::YOUTUBE|self::VIMEO $provider
     * @param string $id the video's ID at its provider
     * @param int|null $start the second it starts at, or null for its beginning
     * @param string|null $hash the hash in an unlisted Vimeo video's URL, which its player needs
     */
    private function __construct(
        public string $provider,
        public string $id,
        public ?int $start = null,
        public ?string $hash = null,
    ) {
    }

    /**
     * The video a YouTube or Vimeo URL shows, with the start it gives, or null for any other URL
     */
    public static function fromUrl(string $url): ?self
    {
        $url = trim($url);
        if (1 !== preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if (false === $parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '/';
        parse_str($parts['query'] ?? '', $query);
        $fragment = $parts['fragment'] ?? '';

        return match ($host) {
            'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com' => self::youTube(self::youTubeId($path, $query), $query),
            'youtu.be' => self::youTube(self::match('~^/(' . self::YOUTUBE_ID . ')/?$~', $path), $query),
            'vimeo.com', 'www.vimeo.com' => self::vimeo($path, null, $fragment),
            'player.vimeo.com' => self::vimeo($path, $query['h'] ?? null, $fragment),
            default => null,
        };
    }

    /**
     * The URL of the embed's player, for an iframe's src
     */
    public function src(): string
    {
        if (self::YOUTUBE === $this->provider) {
            return sprintf('https://www.youtube-nocookie.com/embed/%s%s', $this->id, null === $this->start ? '' : '?start=' . $this->start);
        }

        return sprintf(
            'https://player.vimeo.com/video/%s?%s%s',
            $this->id,
            http_build_query(array_filter(['h' => $this->hash, 'dnt' => 1], static fn (int|string|null $value): bool => null !== $value)),
            null === $this->start ? '' : sprintf('#t=%ds', $this->start),
        );
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private static function youTubeId(string $path, array $query): ?string
    {
        if (1 === preg_match('~^/watch/?$~', $path)) {
            $id = $query['v'] ?? null;

            return is_string($id) && 1 === preg_match('~^' . self::YOUTUBE_ID . '$~', $id) ? $id : null;
        }

        return self::match('~^/(?:embed|shorts|live|v)/(' . self::YOUTUBE_ID . ')/?$~', $path);
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private static function youTube(?string $id, array $query): ?self
    {
        if (null === $id) {
            return null;
        }

        $start = $query['t'] ?? $query['start'] ?? null;

        return new self(self::YOUTUBE, $id, is_string($start) ? self::seconds($start) : null);
    }

    private static function vimeo(string $path, mixed $hash, string $fragment): ?self
    {
        // A video's own page, where an unlisted video's hash follows its ID
        if (1 === preg_match('~^/(\d+)(?:/([0-9a-f]+))?/?$~', $path, $matches)) {
            $id = $matches[1];
            $hash = $matches[2] ?? null;
        } else {
            $id = self::match('~^/(?:video|channels/[^/]+|groups/[^/]+/videos|showcase/\d+/video)/(\d+)/?$~', $path);
        }

        if (null === $id) {
            return null;
        }

        parse_str($fragment, $fragmentQuery);
        $start = $fragmentQuery['t'] ?? null;

        return new self(
            self::VIMEO,
            $id,
            is_string($start) ? self::seconds($start) : null,
            is_string($hash) && 1 === preg_match('~^[0-9a-f]+$~', $hash) ? $hash : null,
        );
    }

    private static function match(string $pattern, string $subject): ?string
    {
        return 1 === preg_match($pattern, $subject, $matches) ? $matches[1] : null;
    }

    /**
     * A start such as 90, 90s or 1m30s in seconds, or null for none, the beginning, or one it can't read
     */
    private static function seconds(string $time): ?int
    {
        if (1 !== preg_match('~^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$~', $time, $matches) || '' === $time) {
            return null;
        }

        $seconds = (int) ($matches[1] ?? 0) * 3600 + (int) ($matches[2] ?? 0) * 60 + (int) ($matches[3] ?? 0);

        return $seconds > 0 ? $seconds : null;
    }
}
