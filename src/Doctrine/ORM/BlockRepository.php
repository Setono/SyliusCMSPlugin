<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\ORM;

use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class BlockRepository extends EntityRepository implements BlockRepositoryInterface
{
    public function findOneByCode(string $code): ?BlockInterface
    {
        $obj = $this->findOneBy([
            'code' => $code,
        ]);
        Assert::nullOrIsInstanceOf($obj, BlockInterface::class);

        return $obj;
    }

    public function findByCodePart(string $phrase, ?int $limit = null): array
    {
        $blocks = $this->createQueryBuilder('o')
            ->andWhere('o.code LIKE :code')
            ->setParameter('code', '%' . $phrase . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        Assert::isArray($blocks);
        Assert::allIsInstanceOf($blocks, BlockInterface::class);

        return $blocks;
    }
}
