<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\BlockInterface;

/**
 * @method BlockInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method BlockInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method BlockInterface[]    findAll()
 * @method BlockInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface BlockRepositoryInterface extends ElementRepositoryInterface
{
    /**
     * @return array<array-key, BlockInterface>
     */
    public function findByCodePart(string $phrase, ?int $limit = null): array;
}
