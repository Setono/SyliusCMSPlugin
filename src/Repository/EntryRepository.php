<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
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

        // A condition's groups are JSON strings in the tree, in the rows or the draft, so they're found with LIKE, as
        // MySQL and PostgreSQL both run it
        $inTree = $inDraft = [];
        foreach (array_values($codes) as $index => $code) {
            $inTree[] = sprintf("o.blockTree LIKE :code_%d ESCAPE '!'", $index);
            $inDraft[] = sprintf("cms_draft.content LIKE :code_%d ESCAPE '!'", $index);
            $queryBuilder->setParameter('code_' . $index, '%' . strtr(CanonicalJson::encode($code), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%');
        }

        /** @var list<EntryInterface> $entries */
        $entries = $queryBuilder
            ->andWhere(implode(' OR ', [
                sprintf('EXISTS (SELECT cms_audience_group.id FROM %s cms_audience_group WHERE cms_audience_group.entry = o AND cms_audience_group.code IN (:codes))', EntryAudienceGroupInterface::class),
                ...$inTree,
                sprintf('EXISTS (SELECT cms_draft.id FROM %s cms_draft WHERE cms_draft.entry = o AND (%s))', DraftInterface::class, implode(' OR ', $inDraft)),
            ]))
            ->orderBy('o.id')
            ->getQuery()
            ->getResult()
        ;

        return $entries;
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
