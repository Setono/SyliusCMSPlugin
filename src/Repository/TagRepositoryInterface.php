<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\TagInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @method TagInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method TagInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method TagInterface[]    findAll()
 * @method TagInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface TagRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<array-key, TagInterface>
     */
    public function findByCodePart(?string $phrase, ?int $limit = null): array;
}
