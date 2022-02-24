<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\ORM;

use Doctrine\ORM\Query\Expr\Join;
use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Repository\CarouselRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class CarouselRepository extends EntityRepository implements CarouselRepositoryInterface
{
    public function findOneByCode(string $code): ?CarouselInterface
    {
        $obj = $this->findOneBy([
            'code' => $code,
        ]);

        Assert::nullOrIsInstanceOf($obj, CarouselInterface::class);

        return $obj;
    }

    public function findByBlock(ElementInterface $element): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.carouselBlocks', 'cb', Join::WITH, 'cb.block = :element')
            ->setParameter(':element', $element)
            ->getQuery()
            ->getResult();
    }
}
