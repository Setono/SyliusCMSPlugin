<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\ImportInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The import resource's repository
 */
class ImportRepository extends EntityRepository implements ImportRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?ImportInterface
    {
        $import = $this->findOneBy(['uuid' => $uuid]);

        return $import instanceof ImportInterface ? $import : null;
    }

    public function findLatest(int $limit): array
    {
        /** @var list<ImportInterface> $imports */
        $imports = $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $imports;
    }
}
