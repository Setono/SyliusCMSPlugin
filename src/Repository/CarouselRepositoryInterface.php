<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\Carousel;
use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
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

    /** @return list<int, Carousel> */
    public function findByBlock(ElementInterface $element): array;
}
