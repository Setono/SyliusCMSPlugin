<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryReferenceInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The reference table resource's repository. Its queries work on MySQL and PostgreSQL alike
 */
class EntryReferenceRepository extends EntityRepository implements EntryReferenceRepositoryInterface
{
    public function findByEntry(EntryInterface $entry): array
    {
        /** @var list<EntryReferenceInterface> $rows */
        $rows = $this->createQueryBuilder('o')
            ->andWhere('o.entry = :entry')
            ->setParameter('entry', $entry)
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $rows;
    }
}
