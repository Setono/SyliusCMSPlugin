<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Setono\SyliusCMSPlugin\Doctrine\SearchPattern;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
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

        /** @var list<EntryInterface> $entries */
        $entries = $queryBuilder
            ->andWhere(implode(' OR ', $this->holdingJsonStrings($queryBuilder, $ids)))
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

    public function createListQueryBuilder(?string $contentType = null): QueryBuilder
    {
        // "translation" is for the filters, and joined apart from the translations fetched, so a filter on it never
        // leaves an entry with only the translations that matched. It's joined first, because Sylius's grid filters
        // reach a field of an association through the first join of it
        return $this->entries($contentType)
            ->addSelect('fetched_translation')
            ->addSelect('audience_group')
            ->leftJoin('o.translations', 'translation')
            ->leftJoin('o.translations', 'fetched_translation')
            ->leftJoin('o.audienceGroups', 'audience_group')
        ;
    }

    public function countEntries(?string $contentType = null): int
    {
        return (int) $this->entries($contentType)->select('COUNT(o.id)')->getQuery()->getSingleScalarResult();
    }

    public function findStartingAfter(\DateTimeImmutable $now, int $limit): array
    {
        return $this->findByDateWindowBoundaryAfter('showFrom', $now, $limit);
    }

    public function findEndingAfter(\DateTimeImmutable $now, int $limit): array
    {
        return $this->findByDateWindowBoundaryAfter('showUntil', $now, $limit);
    }

    public function createNeedingAttentionPaginator(ProblemCountProviderInterface $problemCounts): Pagerfanta
    {
        $queryBuilder = $this->createListQueryBuilder();
        $queryBuilder
            ->andWhere($problemCounts->getNeedsAttentionCondition($queryBuilder, 'o'))
            ->addOrderBy('o.lastEditedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
        ;

        /** @var Pagerfanta<EntryInterface> $paginator */
        $paginator = new Pagerfanta(new QueryAdapter($queryBuilder));

        return $paginator;
    }

    public function findWithDraft(int $limit): array
    {
        /** @var list<EntryInterface> $entries */
        $entries = $this->createQueryBuilder('o')
            ->andWhere(sprintf('EXISTS (SELECT cms_draft.id FROM %s cms_draft WHERE cms_draft.entry = o)', DraftInterface::class))
            ->addOrderBy('o.lastEditedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $entries;
    }

    /**
     * The entries whose date window's start or end, $boundary, comes after $now, the first first. The columns hold UTC
     *
     * @return list<EntryInterface>
     */
    private function findByDateWindowBoundaryAfter(string $boundary, \DateTimeImmutable $now, int $limit): array
    {
        /** @var list<EntryInterface> $entries */
        $entries = $this->createQueryBuilder('o')
            ->andWhere(sprintf('o.%s > :now', $boundary))
            ->setParameter('now', $now->setTimezone(new \DateTimeZone('UTC')), Types::DATETIME_IMMUTABLE)
            ->addOrderBy(sprintf('o.%s', $boundary), 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $entries;
    }

    /**
     * The entries of the content type, or of every content type, as "o". A content type code is the value of the
     * entries' discriminator (ADR 0001), so it names the class that INSTANCE OF filters on, with its subclasses
     */
    private function entries(?string $contentType): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('o');
        if (null === $contentType) {
            return $queryBuilder;
        }

        $class = $this->getClassMetadata()->discriminatorMap[$contentType] ?? throw new \InvalidArgumentException(sprintf('There is no content type "%s"', $contentType));

        // The class goes in the DQL, not in a parameter, because Doctrine's paginator counts without resolving an
        // INSTANCE OF parameter to the discriminator values. It comes from the discriminator map, never from the request
        return $queryBuilder->andWhere(sprintf('o INSTANCE OF %s', $class));
    }
}
