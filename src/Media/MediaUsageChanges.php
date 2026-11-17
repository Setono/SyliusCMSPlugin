<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaUsageInterface;

/**
 * What a write of an entry changes in its rows of the media usage index
 *
 * @internal
 */
final readonly class MediaUsageChanges
{
    /**
     * @param list<MediaUsageInterface> $created new rows, for media items the entry didn't use before
     * @param list<MediaUsageInterface> $updated rows whose draft or published flag changed
     * @param list<MediaUsageInterface> $removed rows of media items the entry no longer uses
     */
    public function __construct(
        public array $created = [],
        public array $updated = [],
        public array $removed = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->created && [] === $this->updated && [] === $this->removed;
    }
}
