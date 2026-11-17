<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Link\Target;

use Setono\SyliusCMSPlugin\Attribute\AsLinkTarget;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Field\MediaItemReferences;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Link\ReferencingLinkTargetInterface;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Media\PermanentUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A document or a video from the media library, by its UUID. It leads to the media item's permanent URL, which follows
 * a replace, named by the media item's name. Media items are shared and public, so one resolves for every shopper while
 * it exists. An image has no permanent URL, so a link to one doesn't resolve. Its reference is a media item reference,
 * so the usage index counts the link as a use. See "Link targets" in docs/spec/extending.md and "Permanent URLs" in
 * docs/spec/media.md
 */
#[AsLinkTarget(code: 'media', label: 'setono_sylius_cms.link_target.media')]
final readonly class MediaLinkTarget implements ReferencingLinkTargetInterface
{
    /**
     * @param ReferenceTypeLoaderInterface $mediaItems the media items, by UUID
     * @param SearchInterface $search the documents and videos
     */
    public function __construct(
        private ReferenceTypeLoaderInterface $mediaItems,
        private PermanentUrlGeneratorInterface $permanentUrlGenerator,
        private SearchInterface $search,
    ) {
    }

    public function resolve(string $ref, RenderContext $context): ?ResolvedLink
    {
        $reference = $this->reference($ref);
        $mediaItem = null === $reference ? null : $this->mediaItems->load([$reference->id], $context)[$reference->id] ?? null;

        return null === $mediaItem ? null : $this->link($mediaItem, $ref, $context);
    }

    public function search(string $query, string $localeCode): iterable
    {
        foreach ($this->search->search($query, $localeCode) as $result) {
            yield $result->jsonSerialize();
        }
    }

    public function reference(string $ref): ?Reference
    {
        return MediaItemReferences::isUuid($ref) ? MediaItemReferences::reference($ref) : null;
    }

    public function link(object $record, string $ref, RenderContext $context): ?ResolvedLink
    {
        if (!$record instanceof MediaItemInterface) {
            return null;
        }

        $url = $this->permanentUrlGenerator->generate($record);

        return null === $url ? null : new ResolvedLink('media', $ref, $url, $record->getName());
    }
}
