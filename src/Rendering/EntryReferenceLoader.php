<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;

/**
 * Loads the entries references name, by UUID: those the shopper may see in the channel and locale, through the visible
 * entries query, so a link to an unpublished entry, or to one that isn't translated or not for the shopper's audience,
 * isn't rendered. The visible query is per content type, so it finds the entries first, then keeps the visible ones
 * with one query per content type among them. See "References to unavailable entries" in docs/spec/localization.md
 *
 * @internal
 */
final readonly class EntryReferenceLoader implements ReferenceTypeLoaderInterface
{
    public function __construct(
        private EntryRepositoryInterface $entries,
        private ContentTypeRegistryInterface $contentTypes,
        private VisibleEntriesQueryInterface $visibleEntries,
    ) {
    }

    public function load(array $ids, RenderContext $context): array
    {
        /** @var array<string, array<string, EntryInterface>> $byContentType content type code => UUID => entry */
        $byContentType = [];
        foreach ($this->entries->findByUuids($ids) as $entry) {
            try {
                $byContentType[$this->contentTypes->getByEntry($entry)->code][$entry->getUuid()] = $entry;
            } catch (UnknownContentTypeException) {
                // An entry of a content type the code no longer has shows nowhere
            }
        }

        $loaded = [];
        foreach ($byContentType as $contentType => $entries) {
            /** @var list<string> $visible */
            $visible = $this->visibleEntries->createQueryBuilder($contentType, $context)
                ->select('entry.uuid')
                ->andWhere('entry.uuid IN (:cms_uuids)')
                ->setParameter('cms_uuids', array_keys($entries))
                ->getQuery()
                ->getSingleColumnResult()
            ;

            foreach ($visible as $uuid) {
                $loaded[$uuid] = $entries[$uuid];
            }
        }

        return $loaded;
    }

    public function existing(array $ids): array
    {
        return $this->entries->findExistingUuids($ids);
    }
}
