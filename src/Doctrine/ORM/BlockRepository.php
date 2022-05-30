<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\ORM;

use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Webmozart\Assert\Assert;

class BlockRepository extends ElementRepository implements BlockRepositoryInterface
{
    public function findByCodePart(string $phrase, ?int $limit = null): array
    {
        $blocks = $this->createQueryBuilder('o')
            ->andWhere('o.code LIKE :code')
            ->setParameter('code', '%' . $phrase . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        Assert::allIsInstanceOf($blocks, BlockInterface::class);

        return $blocks;
    }
}
