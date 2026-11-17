<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * What a media item is. The MIME types each kind allows are config: media.types. See "What the library holds" in
 * docs/spec/media.md
 */
enum MediaKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Document = 'document';

    /**
     * The kinds that have a permanent URL, which links to them lead to: documents and videos. An image has none, since
     * templates render images through cms_image(). See "Permanent URLs" in docs/spec/media.md
     *
     * @return list<self>
     */
    public static function withPermanentUrl(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $kind): bool => $kind->hasPermanentUrl()));
    }

    public function hasPermanentUrl(): bool
    {
        return self::Image !== $this;
    }
}
