<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\Search;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Context\EntryTitle;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Doctrine\SearchPattern;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * Searches the entries of the content types with a URL of their own, which links lead to, by their title and slug in
 * any locale, ignoring case, published or not: an editor may link to an entry before it's out. The picker of an Entry
 * or Entries field that names a content type narrows the search to its entries, with or without a URL of their own,
 * with the request's query parameter `contentType`, its code, such as a blog post's categories picker. Looking entries
 * up by UUID finds those of every content type, so a field names what it holds. Each is named by its title in the
 * admin user's locale, along its fallback chain, newest first. A disabled content type's entries are never found
 *
 * @internal
 */
final readonly class EntrySearch implements SearchInterface
{
    /** The most results it answers */
    public const LIMIT = 20;

    /**
     * @param class-string<EntryInterface> $entryClass the class of every entry, the root of the content types' classes
     */
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private string $entryClass,
        private ContentTypeRegistryInterface $contentTypes,
        private FallbackValues $fallbackValues,
        private RequestStack $requestStack,
    ) {
    }

    public function search(string $query, string $localeCode): array
    {
        $contentType = $this->requestStack->getCurrentRequest()?->query->all()['contentType'] ?? null;
        $contentTypes = match (true) {
            null === $contentType => array_filter($this->contentTypes->enabled(), static fn (ContentTypeMetadata $contentType): bool => $contentType->hasOwnUrl()),
            is_string($contentType) && $this->contentTypes->isEnabled($contentType) => [$this->contentTypes->get($contentType)],
            default => [],
        };

        $entries = $this->entries($contentTypes)
            ->andWhere(sprintf(
                'EXISTS (SELECT matched.id FROM %1$s matched WHERE matched.translatable = entry AND (LOWER(matched.title) LIKE :query %2$s OR LOWER(matched.slug) LIKE :query %2$s))',
                EntryTranslation::class,
                SearchPattern::ESCAPE,
            ))
            ->setParameter('query', SearchPattern::containing($query))
            ->setMaxResults(self::LIMIT)
        ;

        return $this->results($entries, $localeCode);
    }

    public function find(array $refs, string $localeCode): array
    {
        $uuids = array_values(array_map(strtolower(...), array_filter($refs, Uuid::isValid(...))));
        if ([] === $uuids) {
            return [];
        }

        // A disabled content type is hidden from the builder
        return $this->results($this->entries($this->contentTypes->enabled())->andWhere('entry.uuid IN (:uuids)')->setParameter('uuids', $uuids), $localeCode);
    }

    /**
     * The entries of the content types, newest first
     *
     * @param array<array-key, ContentTypeMetadata> $contentTypes
     */
    private function entries(array $contentTypes): QueryBuilder
    {
        $classes = array_map(static fn (ContentTypeMetadata $contentType): string => $contentType->modelClass, $contentTypes);

        $queryBuilder = EntityManagers::of($this->managerRegistry, $this->entryClass)->createQueryBuilder()
            ->select('entry')
            ->from($this->entryClass, 'entry')
            ->addOrderBy('entry.id', 'DESC')
        ;

        // The classes go in the DQL, from the registry, never from the request
        return $queryBuilder->andWhere([] === $classes ? '1 = 0' : implode(' OR ', array_map(static fn (string $class): string => sprintf('entry INSTANCE OF %s', $class), $classes)));
    }

    /**
     * @return list<SearchResult>
     */
    private function results(QueryBuilder $queryBuilder, string $localeCode): array
    {
        /** @var list<EntryInterface> $entries */
        $entries = $queryBuilder->getQuery()->getResult();

        return array_map(fn (EntryInterface $entry): SearchResult => new SearchResult($entry->getUuid(), EntryTitle::inLocale($entry, $localeCode, $this->fallbackValues)), $entries);
    }
}
