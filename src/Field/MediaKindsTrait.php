<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * The kinds of media item a Media or Media list field takes
 *
 * @internal
 */
trait MediaKindsTrait
{
    /**
     * @return list<MediaKind>
     */
    public function mediaKinds(): array
    {
        return array_map(MediaKind::from(...), $this->kinds);
    }

    public function takes(MediaItemInterface $mediaItem): bool
    {
        return in_array($mediaItem->getKind()->value, $this->kinds, true);
    }

    /**
     * @param array<array-key, mixed> $kinds
     *
     * @throws \InvalidArgumentException for no kind, or one that isn't a media item's kind
     */
    private static function assertKinds(array $kinds): void
    {
        if ([] === $kinds) {
            throw new \InvalidArgumentException(sprintf('A %s field must take at least one kind of media item', static::class));
        }

        foreach ($kinds as $kind) {
            if (!is_string($kind) || null === MediaKind::tryFrom($kind)) {
                throw new \InvalidArgumentException(sprintf('The kinds of a %s field are image, video and document, got %s', static::class, is_string($kind) ? sprintf('"%s"', $kind) : get_debug_type($kind)));
            }
        }
    }
}
