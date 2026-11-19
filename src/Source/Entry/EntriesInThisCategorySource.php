<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Entry;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Collection\CollectionMetadata;
use Setono\SyliusCMSPlugin\Collection\Collections;
use Setono\SyliusCMSPlugin\Collection\IndexedFields;
use Setono\SyliusCMSPlugin\Collection\ReferenceTable;
use Setono\SyliusCMSPlugin\Context\EntryContext;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\EntryContextAwareInterface;
use Setono\SyliusCMSPlugin\Source\ListedContentTypeAwareInterface;
use Setono\SyliusCMSPlugin\Source\SourceInterface;

/**
 * "In this category": on a category's page, the entries the shopper may see of the collection whose category type it
 * is that are in the category, such as a blog category's posts, in the collection's order, then by their ID. It's what
 * makes a category's URL its archive. It fits only a category as the host page's entry, and yields nothing elsewhere.
 * The category filter is an EXISTS on the reference table, so it pages in SQL. See "Entry sources" in
 * docs/spec/sources.md and "Listings" in docs/spec/collections.md
 */
#[AsSource(code: self::CODE, label: 'setono_sylius_cms.source.entries_in_this_category', of: EntryInterface::class, needs: [EntryContext::ENTRY])]
final readonly class EntriesInThisCategorySource implements SourceInterface, EntryContextAwareInterface, ListedContentTypeAwareInterface
{
    public const CODE = 'entries_in_this_category';

    public function __construct(
        private VisibleEntriesQueryInterface $visibleEntries,
        private ContentTypeRegistryInterface $contentTypes,
        private Collections $collections,
        private IndexedFields $indexedFields,
    ) {
    }

    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        $category = $context->get(EntryContext::ENTRY);
        if (!$category instanceof EntryInterface) {
            return new ArrayAdapter([]);
        }

        try {
            $collection = $this->collectionOf($this->contentTypes->getByEntry($category)->code);
        } catch (UnknownContentTypeException) {
            return new ArrayAdapter([]);
        }

        $categoryField = null === $collection ? null : $this->indexedFields->categoryField($collection->entries);
        if (null === $collection || null === $categoryField) {
            return new ArrayAdapter([]);
        }

        $queryBuilder = $this->visibleEntries->createQueryBuilder($collection->code(), $context);
        $queryBuilder->andWhere(ReferenceTable::holding($queryBuilder, 'entry', $categoryField->name, [$category->getUuid()]));
        $collection->entries->order->applyTo($queryBuilder, 'entry');

        // No collection is fetched with the entries, so the paginator pages the query as it is
        return new QueryAdapter($queryBuilder, false);
    }

    /**
     * A category of a collection, such as a blog category
     */
    public function supportsEntryContentType(string $code): bool
    {
        return null !== $this->collectionOf($code);
    }

    /**
     * The collection's content type whose category type the host page's entry is, such as blog_post in a blog category
     */
    public function getListedContentType(object $params, ?string $hostContentType): ?string
    {
        return null === $hostContentType ? null : $this->collectionOf($hostContentType)?->code();
    }

    /**
     * The collection whose categories are of the content type, or null when it's no collection's category type
     */
    private function collectionOf(string $contentType): ?CollectionMetadata
    {
        $collection = $this->collections->of($contentType);

        return null !== $collection && $contentType === $collection->categories?->code ? $collection : null;
    }
}
