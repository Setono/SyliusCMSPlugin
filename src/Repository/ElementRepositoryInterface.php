<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @method ElementInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method ElementInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method ElementInterface[]    findAll()
 * @method ElementInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface ElementRepositoryInterface extends RepositoryInterface
{
    public function findOneByCode(string $code): ?ElementInterface;
}
