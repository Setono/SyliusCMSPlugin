<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Event\MediaItemSaved;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Checks the metadata against the media item's kind first: only an image has alt text and a focal point, and only a
 * video has a poster, which is an image. Alt text is per locale of the store, the locales of its channels
 */
final readonly class MediaItemSaver implements MediaItemSaverInterface
{
    private const MAX_LENGTH = 255;

    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
        private MediaLocales $locales,
    ) {
    }

    public function save(MediaItemInterface $mediaItem, MediaItemMetadata $metadata): void
    {
        $metadata = self::trimmed($metadata);
        $before = MediaItemMetadata::of($mediaItem);
        if ($metadata->equals($before)) {
            return;
        }

        $this->validate($mediaItem, $metadata, $before);

        $manager = EntityManagers::of($this->managerRegistry, $mediaItem::class);
        $manager->wrapInTransaction(function () use ($mediaItem, $metadata): void {
            $mediaItem->setName($metadata->name);
            $mediaItem->setFolder($metadata->folder);
            $mediaItem->setCredit($metadata->credit);
            foreach (array_unique([...array_keys($mediaItem->getAltTexts()), ...array_keys($metadata->altTexts)]) as $locale) {
                $mediaItem->setAltText($locale, $metadata->altTexts[$locale] ?? null);
            }
            $mediaItem->setFocalPoint($metadata->focalPoint);
            $mediaItem->setPoster($metadata->poster);
            $mediaItem->setChangedAt($this->clock->now());
        });

        // After the transaction commits
        $this->eventDispatcher->dispatch(new MediaItemSaved($mediaItem->getUuid()));
    }

    /**
     * @throws InvalidMediaChangeException
     */
    private function validate(MediaItemInterface $mediaItem, MediaItemMetadata $metadata, MediaItemMetadata $before): void
    {
        if ('' === $metadata->name) {
            throw new InvalidMediaChangeException('A media item needs a name');
        }

        if (mb_strlen($metadata->name) > self::MAX_LENGTH || mb_strlen($metadata->credit ?? '') > self::MAX_LENGTH) {
            throw new InvalidMediaChangeException(sprintf('A media item\'s name and credit can be at most %d characters long', self::MAX_LENGTH));
        }

        $image = MediaKind::Image === $mediaItem->getKind();
        if (!$image && ([] !== $metadata->altTexts || !$metadata->focalPoint->equals($before->focalPoint))) {
            throw new InvalidMediaChangeException('Only an image has alt text and a focal point');
        }

        // Alt text changes only in the store's locales. What a media item has in another locale, such as one a channel no
        // longer has, stays as it is
        $changed = array_keys(array_diff_assoc($metadata->altTexts, $before->altTexts) + array_diff_assoc($before->altTexts, $metadata->altTexts));
        $unknown = array_diff($changed, $this->locales->codes());
        if ([] !== $unknown) {
            throw new InvalidMediaChangeException(sprintf('The store has no locale %s', implode(', ', $unknown)));
        }

        if ($metadata->poster !== $before->poster && null !== $metadata->poster &&
            (MediaKind::Video !== $mediaItem->getKind() || MediaKind::Image !== $metadata->poster->getKind())) {
            throw new InvalidMediaChangeException('Only a video has a poster, and its poster is an image');
        }
    }

    private static function trimmed(MediaItemMetadata $metadata): MediaItemMetadata
    {
        $credit = null === $metadata->credit ? '' : trim($metadata->credit);

        return new MediaItemMetadata(
            trim($metadata->name),
            $metadata->folder,
            '' === $credit ? null : $credit,
            $metadata->altTexts,
            $metadata->focalPoint,
            $metadata->poster,
        );
    }
}
