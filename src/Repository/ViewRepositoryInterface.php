<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @method ViewInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method ViewInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method ViewInterface[]    findAll()
 * @method ViewInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface ViewRepositoryInterface extends RepositoryInterface
{
    public function findOneByCode(string $code): ?ViewInterface;
}
