<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Collection\ReferenceTable;
use Setono\SyliusCMSPlugin\Doctrine\SearchPattern;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\ContentTypeModels;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The entry resource's repository
 */
class EntryRepository extends EntityRepository implements EntryRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?EntryInterface
    {
        $entry = $this->findOneBy(['uuid' => $uuid]);

        return $entry instanceof EntryInterface ? $entry : null;
    }

    public function findByUuids(array $uuids): array
    {
        if ([] === $uuids) {
            return [];
        }

        /** @var list<EntryInterface> $entries */
        $entries = $this->createQueryBuilder('o')
            ->andWhere('o.uuid IN (:uuids)')
            ->setParameter('uuids', $uuids)
            ->getQuery()
            ->getResult()
        ;

        return $entries;
    }

    public function findExistingUuids(array $uuids): array
    {
        if ([] === $uuids) {
            return [];
        }

        /** @var list<string> $existing */
        $existing = $this->createQueryBuilder('o')
            ->select('o.uuid')
            ->andWhere('o.uuid IN (:uuids)')
            ->setParameter('uuids', $uuids)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        return $existing;
    }

    public function findOneById(int $id): ?EntryInterface
    {
        $entry = $this->find($id);

        return $entry instanceof EntryInterface ? $entry : null;
    }

    public function iterateAll(): iterable
    {
        /** @var iterable<EntryInterface> $entries */
        $entries = $this->createQueryBuilder('entry')->orderBy('entry.id')->getQuery()->toIterable();

        return $entries;
    }

    public function findByContentType(string $contentType): array
    {
        /** @var list<EntryInterface> $entries */
        $entries = $this->entries($contentType)->orderBy('o.id')->getQuery()->getResult();

        return $entries;
    }

    public function search(string $search, int $limit, array $hiddenContentTypes = []): array
    {
        $translationClass = $this->getClassMetadata()->getAssociationTargetClass('translations');

        /** @var list<EntryInterface> $entries */
        $entries = $this->entries(null, $hiddenContentTypes)
            ->andWhere(sprintf(
                'EXISTS (SELECT matched.id FROM %1$s matched WHERE matched.translatable = o AND (LOWER(matched.title) LIKE :search %2$s OR LOWER(matched.slug) LIKE :search %2$s))',
                $translationClass,
                SearchPattern::ESCAPE,
            ))
            ->setParameter('search', SearchPattern::containing($search))
            ->orderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $entries;
    }

    public function findByCustomerGroupCodes(array $codes): array
    {
        if ([] === $codes) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder('o')
            ->setParameter('codes', $codes)
        ;

        /** @var list<EntryInterface> $entries */
        $entries = $queryBuilder
            ->andWhere(implode(' OR ', [
                sprintf('EXISTS (SELECT cms_audience_group.id FROM %s cms_audience_group WHERE cms_audience_group.entry = o AND cms_audience_group.code IN (:codes))', EntryAudienceGroupInterface::class),
                // A condition's groups are JSON strings in the tree
                ...$this->holdingJsonStrings($queryBuilder, $codes),
            ]))
            ->orderBy('o.id')
            ->getQuery()
            ->getResult()
        ;

        return $entries;
    }

    public function findReferencing(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder('o');
        $conditions = $this->holdingJsonStrings($queryBuilder, $ids);
        // An indexed field's UUIDs are on the reference table, where the published ones are, such as a post's categories
        $conditions[] = ReferenceTable::holding($queryBuilder, 'o', null, $ids);

        /** @var list<EntryInterface> $entries */
        $entries = $queryBuilder
            ->andWhere(implode(' OR ', $conditions))
            ->orderBy('o.id')
            ->getQuery()
            ->getResult()
        ;

        return $entries;
    }

    /**
     * The conditions on the entry "o" that its rows' block tree, or its draft, holds one of the strings as a JSON string.
     * JSON is found with LIKE, as MySQL and PostgreSQL both run it
     *
     * @param list<string> $strings
     *
     * @return list<string> to join with OR
     */
    private function holdingJsonStrings(QueryBuilder $queryBuilder, array $strings): array
    {
        $inTree = $inDraft = [];
        foreach (array_values($strings) as $index => $string) {
            $inTree[] = sprintf('o.blockTree LIKE :json_string_%d %s', $index, SearchPattern::ESCAPE);
            $inDraft[] = sprintf('cms_draft.content LIKE :json_string_%d %s', $index, SearchPattern::ESCAPE);
            $queryBuilder->setParameter('json_string_' . $index, SearchPattern::holding(CanonicalJson::encode($string)));
        }

        return [
            ...$inTree,
            sprintf('EXISTS (SELECT cms_draft.id FROM %s cms_draft WHERE cms_draft.entry = o AND (%s))', DraftInterface::class, implode(' OR ', $inDraft)),
        ];
    }

    public function createListQueryBuilder(?string $contentType = null, array $hiddenContentTypes = []): QueryBuilder
    {
        $queryBuilder = $this->entries($contentType, $hiddenContentTypes);

        // Before the sorting the grid adds, which then sorts entries in the same position
        if (null !== $contentType && ContentType::ORDER_BY_POSITION === AttributeReader::contentType($this->classOf($contentType))?->order) {
            $queryBuilder->addOrderBy('o.position', 'ASC');
        }

        return self::listed($queryBuilder);
    }

    public function createCollectionListQueryBuilder(string $contentType, array $hiddenContentTypes = []): QueryBuilder
    {
        $class = $this->classOf($contentType);
        $classes = [$class];

        $categoryType = AttributeReader::contentType($class)?->categoryType;
        if (null !== $categoryType) {
            /** @var array<string, class-string> $discriminatorMap */
            $discriminatorMap = array_diff_key($this->getClassMetadata()->discriminatorMap, array_flip($hiddenContentTypes));
            $categoryClass = ContentTypeModels::namedBy($categoryType, $discriminatorMap);
            if (null !== $categoryClass) {
                $classes[] = $categoryClass;
            }
        }

        // The classes go in the DQL, as in entries(), from the discriminator map, never from the request
        return self::listed($this->createQueryBuilder('o')->andWhere(implode(' OR ', array_map(static fn (string $class): string => sprintf('o INSTANCE OF %s', $class), $classes))));
    }

    /**
     * An admin list's query, with the translations and audience groups its rows show fetched. "translation" is for the
     * filters, and joined apart from the translations fetched, so a filter on it never leaves an entry with only the
     * translations that matched. It's joined first, because Sylius's grid filters reach a field of an association
     * through the first join of it
     */
    private static function listed(QueryBuilder $queryBuilder): QueryBuilder
    {
        return $queryBuilder
            ->addSelect('fetched_translation')
            ->addSelect('audience_group')
            ->leftJoin('o.translations', 'translation')
            ->leftJoin('o.translations', 'fetched_translation')
            ->leftJoin('o.audienceGroups', 'audience_group')
        ;
    }

    public function countEntries(?string $contentType = null, array $hiddenContentTypes = []): int
    {
        return (int) $this->entries($contentType, $hiddenContentTypes)->select('COUNT(o.id)')->getQuery()->getSingleScalarResult();
    }

    public function findStartingAfter(\DateTimeImmutable $now, int $limit, array $hiddenContentTypes = []): array
    {
        return $this->findByDateWindowBoundaryAfter('showFrom', $now, $limit, $hiddenContentTypes);
    }

    public function findEndingAfter(\DateTimeImmutable $now, int $limit, array $hiddenContentTypes = []): array
    {
        return $this->findByDateWindowBoundaryAfter('showUntil', $now, $limit, $hiddenContentTypes);
    }

    public function createNeedingAttentionPaginator(ProblemCountProviderInterface $problemCounts, array $hiddenContentTypes = []): Pagerfanta
    {
        $queryBuilder = $this->createListQueryBuilder(null, $hiddenContentTypes);
        $queryBuilder
            ->andWhere($problemCounts->getNeedsAttentionCondition($queryBuilder, 'o'))
            ->addOrderBy('o.lastEditedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
        ;

        /** @var Pagerfanta<EntryInterface> $paginator */
        $paginator = new Pagerfanta(new QueryAdapter($queryBuilder));

        return $paginator;
    }

    public function findDraftsInProgress(int $limit, array $hiddenContentTypes = []): array
    {
        /** @var list<EntryInterface> $entries */
        $entries = $this->entries(null, $hiddenContentTypes)
            ->andWhere(sprintf('o.lastPublishedAt IS NULL OR EXISTS (SELECT cms_draft.id FROM %s cms_draft WHERE cms_draft.entry = o)', DraftInterface::class))
            ->addOrderBy('o.lastEditedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $entries;
    }

    /**
     * The entries whose date window's start or end, $boundary, comes after $now, the first first, of those whose
     * boundary changes what shoppers see: enabled, and published, or with a publish scheduled for it or before that
     * hasn't failed. The columns hold UTC
     *
     * @param list<string> $hiddenContentTypes
     *
     * @return list<EntryInterface>
     */
    private function findByDateWindowBoundaryAfter(string $boundary, \DateTimeImmutable $now, int $limit, array $hiddenContentTypes): array
    {
        /** @var list<EntryInterface> $entries */
        $entries = $this->entries(null, $hiddenContentTypes)
            ->andWhere(sprintf('o.%s > :now', $boundary))
            ->andWhere('o.enabled = :enabled')
            ->andWhere(sprintf(
                'o.lastPublishedAt IS NOT NULL OR EXISTS (SELECT cms_scheduled.id FROM %s cms_scheduled WHERE cms_scheduled.entry = o AND cms_scheduled.failureReason IS NULL AND cms_scheduled.publishAt <= o.%s)',
                ScheduledPublishInterface::class,
                $boundary,
            ))
            ->setParameter('now', $now->setTimezone(new \DateTimeZone('UTC')), Types::DATETIME_IMMUTABLE)
            ->setParameter('enabled', true, Types::BOOLEAN)
            ->addOrderBy(sprintf('o.%s', $boundary), 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $entries;
    }

    /**
     * The entries of the content type, or of every content type but the hidden ones, as "o". A content type code is
     * the value of the entries' discriminator (ADR 0001), so it names the class that INSTANCE OF filters on, with its
     * subclasses
     *
     * @param list<string> $hiddenContentTypes
     */
    private function entries(?string $contentType, array $hiddenContentTypes = []): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('o');

        // The class goes in the DQL, not in a parameter, because Doctrine's paginator counts without resolving an
        // INSTANCE OF parameter to the discriminator values. It comes from the discriminator map, never from the request
        if (null !== $contentType) {
            return $queryBuilder->andWhere(sprintf('o INSTANCE OF %s', $this->classOf($contentType)));
        }

        return HiddenContentTypes::exclude($queryBuilder, 'o', $this->getClassMetadata(), array_values($hiddenContentTypes));
    }

    /**
     * The model of the content type, which its code maps to as the entries' discriminator value
     *
     * @return class-string
     */
    private function classOf(string $contentType): string
    {
        return $this->getClassMetadata()->discriminatorMap[$contentType] ?? throw new \InvalidArgumentException(sprintf('There is no content type "%s"', $contentType));
    }
}
