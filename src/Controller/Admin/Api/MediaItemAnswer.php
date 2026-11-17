<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Media\MediaUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaUsageRepositoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A media item as the media endpoints answer with it: what was read from its file, its metadata, whether an image misses
 * alt text in one or more of the store's locales, its current file's URL and how many entries use it. Times are in UTC
 *
 * @internal
 */
final class MediaItemAnswer implements ResetInterface
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    /** @var list<string>|null */
    private ?array $storeLocales = null;

    public function __construct(
        private readonly MediaUrlGeneratorInterface $urlGenerator,
        private readonly TranslationLocaleProviderInterface $translationLocaleProvider,
        private readonly MediaUsageRepositoryInterface $usages,
    ) {
    }

    /**
     * Several media items, with their usage counts read in one query
     *
     * @param list<MediaItemInterface> $mediaItems
     *
     * @return list<array<string, mixed>>
     */
    public function list(array $mediaItems): array
    {
        $usageCounts = $this->usages->countByMediaItems(array_map(static fn (MediaItemInterface $mediaItem): string => $mediaItem->getUuid(), $mediaItems));

        return array_map(fn (MediaItemInterface $mediaItem): array => $this($mediaItem, $usageCounts[$mediaItem->getUuid()] ?? 0), $mediaItems);
    }

    /**
     * @param int|null $usageCount how many entries use it, as the usage index has it, or null to read it
     *
     * @return array<string, mixed>
     */
    public function __invoke(MediaItemInterface $mediaItem, ?int $usageCount = null): array
    {
        $image = MediaKind::Image === $mediaItem->getKind();
        $altTexts = $mediaItem->getAltTexts();
        $focalPoint = $mediaItem->getFocalPoint();

        return [
            'uuid' => $mediaItem->getUuid(),
            'kind' => $mediaItem->getKind()->value,
            'name' => $mediaItem->getName(),
            'originalFilename' => $mediaItem->getOriginalFilename(),
            'mimeType' => $mediaItem->getMimeType(),
            'size' => $mediaItem->getSize(),
            'width' => $mediaItem->getWidth(),
            'height' => $mediaItem->getHeight(),
            'credit' => $mediaItem->getCredit(),
            // An object even when it has none
            'altTexts' => (object) $altTexts,
            'missingAlt' => $image && [] !== array_diff($this->storeLocales(), array_keys($altTexts)),
            'focalPoint' => $image ? ['x' => $focalPoint->x, 'y' => $focalPoint->y] : null,
            'poster' => $mediaItem->getPoster()?->getUuid(),
            'folder' => $mediaItem->getFolder()?->getUuid(),
            'url' => $this->urlGenerator->generate($mediaItem->getPath()),
            // How many entries use it, which the library shows, and which must be none for it to be deleted
            'usageCount' => $usageCount ?? $this->usages->countByMediaItems([$mediaItem->getUuid()])[$mediaItem->getUuid()] ?? 0,
            'createdAt' => $mediaItem->getCreatedAt()->format(self::DATE_FORMAT),
            'changedAt' => $mediaItem->getChangedAt()->format(self::DATE_FORMAT),
        ];
    }

    public function reset(): void
    {
        $this->storeLocales = null;
    }

    /**
     * The store's locales, read once per request
     *
     * @return list<string>
     */
    private function storeLocales(): array
    {
        return $this->storeLocales ??= array_values($this->translationLocaleProvider->getDefinedLocalesCodes());
    }
}
