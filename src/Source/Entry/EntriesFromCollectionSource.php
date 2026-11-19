<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Entry;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Collection\IndexedFields;
use Setono\SyliusCMSPlugin\Collection\ReferenceTable;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\ListedContentTypeAwareInterface;
use Setono\SyliusCMSPlugin\Source\SourceInterface;

/**
 * "From a collection": the entries of one collection the shopper may see, such as the blog's posts, optionally only
 * those in any of the chosen categories, in the content type's order, then by their ID. The category filter is an
 * EXISTS on the reference table, so it pages in SQL. See "Entry sources" in docs/spec/sources.md
 */
#[AsSource(code: self::CODE, label: 'setono_sylius_cms.source.entries_from_collection', of: EntryInterface::class, params: EntriesFromCollectionParams::class)]
final readonly class EntriesFromCollectionSource implements SourceInterface, ListedContentTypeAwareInterface
{
    public const CODE = 'entries_from_collection';

    public function __construct(
        private VisibleEntriesQueryInterface $visibleEntries,
        private ContentTypeRegistryInterface $contentTypes,
        private IndexedFields $indexedFields,
    ) {
    }

    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        if (!$params instanceof EntriesFromCollectionParams) {
            throw new \InvalidArgumentException(sprintf('The %s source takes %s, got %s', self::CODE, EntriesFromCollectionParams::class, $params::class));
        }

        // The parameter's choices are the collections, so one that no longer is narrows to nothing before it gets here
        if (null === $params->contentType || !$this->contentTypes->has($params->contentType)) {
            return new ArrayAdapter([]);
        }

        $contentType = $this->contentTypes->get($params->contentType);
        $queryBuilder = $this->visibleEntries->createQueryBuilder($contentType->code, $context);

        if (null !== $params->categories) {
            $categoryField = $this->indexedFields->categoryField($contentType);
            $queryBuilder->andWhere(null === $categoryField ? '1 = 0' : ReferenceTable::holding(
                $queryBuilder,
                'entry',
                $categoryField->name,
                array_map(static fn (EntryInterface $category): string => $category->getUuid(), $params->categories->toArray()),
            ));
        }

        $contentType->order->applyTo($queryBuilder, 'entry');

        // No collection is fetched with the entries, so the paginator pages the query as it is
        return new QueryAdapter($queryBuilder, false);
    }

    public function getListedContentType(object $params, ?string $hostContentType): ?string
    {
        return $params instanceof EntriesFromCollectionParams ? $params->contentType : null;
    }
}
