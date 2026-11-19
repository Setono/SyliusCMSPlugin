<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Entry;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
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
 * "Related": the other entries the shopper may see of the host page's entry's content type that share at least one
 * category with it, such as a blog post's related posts, newest first, then by their ID. Newest first is the content
 * type's order when that's a date descending, such as Blog post's publishedAt, and by when they were created otherwise.
 * The entry's categories are read as it's shown, so a preview lists by the categories being edited, and the others'
 * from the reference table, which holds their published ones, so it pages in SQL. It fits only an entry of a collection
 * with categories as the host page's entry, and yields nothing elsewhere. See "Entry sources" in docs/spec/sources.md
 */
#[AsSource(code: self::CODE, label: 'setono_sylius_cms.source.related_entries', of: EntryInterface::class, needs: [EntryContext::ENTRY])]
final readonly class RelatedEntriesSource implements SourceInterface, EntryContextAwareInterface, ListedContentTypeAwareInterface
{
    public const CODE = 'related_entries';

    public function __construct(
        private VisibleEntriesQueryInterface $visibleEntries,
        private ContentTypeRegistryInterface $contentTypes,
        private Collections $collections,
        private IndexedFields $indexedFields,
    ) {
    }

    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        $entry = $context->get(EntryContext::ENTRY);
        if (!$entry instanceof EntryInterface) {
            return new ArrayAdapter([]);
        }

        try {
            $contentType = $this->contentTypes->getByEntry($entry);
        } catch (UnknownContentTypeException) {
            return new ArrayAdapter([]);
        }

        $categoryField = $this->supportsEntryContentType($contentType->code) ? $this->indexedFields->categoryField($contentType) : null;
        $categories = $this->indexedFields->categoriesOf($entry);
        if (null === $categoryField || [] === $categories) {
            return new ArrayAdapter([]);
        }

        $queryBuilder = $this->visibleEntries->createQueryBuilder($contentType->code, $context);
        $queryBuilder
            ->andWhere(ReferenceTable::holding($queryBuilder, 'entry', $categoryField->name, $categories))
            ->andWhere('entry.uuid <> :cms_source_host_entry')
            ->setParameter('cms_source_host_entry', $entry->getUuid())
        ;
        $contentType->order->newestFirst($contentType->modelClass)->applyTo($queryBuilder, 'entry');

        // No collection is fetched with the entries, so the paginator pages the query as it is
        return new QueryAdapter($queryBuilder, false);
    }

    /**
     * An entry of a collection with categories, such as a blog post
     */
    public function supportsEntryContentType(string $code): bool
    {
        return $this->collections->hasCategories($code);
    }

    /**
     * The host page's entry's own content type, when it fits
     */
    public function getListedContentType(object $params, ?string $hostContentType): ?string
    {
        return null !== $hostContentType && $this->supportsEntryContentType($hostContentType) ? $hostContentType : null;
    }
}
