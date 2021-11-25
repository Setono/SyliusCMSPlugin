<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @method CarouselInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method CarouselInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method CarouselInterface[]    findAll()
 * @method CarouselInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface CarouselRepositoryInterface extends RepositoryInterface
{
    public function findOneByCode(string $code): ?CarouselInterface;
}
