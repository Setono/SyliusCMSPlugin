<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * What editors change about a media item: its name, folder, credit, alt text per locale, focal point and poster. The
 * library saves it at once. See "Metadata" in docs/spec/media.md
 */
final readonly class MediaItemMetadata
{
    /**
     * @param array<string, string> $altTexts by locale, without empty ones
     */
    public function __construct(
        public string $name,
        public ?MediaFolderInterface $folder,
        public ?string $credit,
        public array $altTexts,
        public FocalPoint $focalPoint,
        public ?MediaItemInterface $poster,
    ) {
    }

    public static function of(MediaItemInterface $mediaItem): self
    {
        return new self(
            $mediaItem->getName(),
            $mediaItem->getFolder(),
            $mediaItem->getCredit(),
            $mediaItem->getAltTexts(),
            $mediaItem->getFocalPoint(),
            $mediaItem->getPoster(),
        );
    }

    /**
     * @param array<string, string|null> $altTexts the alt texts that change, by locale. An empty or null one is removed
     */
    public function withAltTexts(array $altTexts): self
    {
        $merged = $this->altTexts;
        foreach ($altTexts as $locale => $altText) {
            if (null === $altText || '' === trim($altText)) {
                unset($merged[$locale]);
            } else {
                $merged[$locale] = $altText;
            }
        }

        ksort($merged);

        return new self($this->name, $this->folder, $this->credit, $merged, $this->focalPoint, $this->poster);
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name &&
            $this->folder === $other->folder &&
            $this->credit === $other->credit &&
            $this->altTexts === $other->altTexts &&
            $this->focalPoint->equals($other->focalPoint) &&
            $this->poster === $other->poster;
    }
}
