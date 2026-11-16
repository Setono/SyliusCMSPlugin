<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\OldSlugInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The old slug resource's repository. Old slugs are ordered by id, the order they were recorded in
 */
class OldSlugRepository extends EntityRepository implements OldSlugRepositoryInterface
{
    public function findByEntry(EntryInterface $entry): array
    {
        /** @var list<OldSlugInterface> $oldSlugs */
        $oldSlugs = $this->createQueryBuilder('o')
            ->andWhere('o.entry = :entry')
            ->setParameter('entry', $entry)
            ->addOrderBy('o.localeCode', 'ASC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $oldSlugs;
    }
}
