<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * The effective upload limit and the MIME types of each kind of media item, as the builder's registry and the media
 * library's screen hand them to the app, so it checks a file before uploading it. See "What the library holds" in
 * docs/spec/media.md
 *
 * @internal
 */
final readonly class MediaLimits
{
    public function __construct(
        private UploadLimit $uploadLimit,
        private MediaTypes $mediaTypes,
    ) {
    }

    /**
     * @return array{maxUploadSize: int, types: array<string, list<string>>}
     */
    public function toArray(): array
    {
        return ['maxUploadSize' => $this->uploadLimit->bytes(), 'types' => $this->mediaTypes->all()];
    }
}
