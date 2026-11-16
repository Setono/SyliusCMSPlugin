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
}
