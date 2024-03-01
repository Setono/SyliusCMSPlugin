<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Webmozart\Assert\Assert;

class CarouselRepository extends ElementRepository implements CarouselRepositoryInterface
{
    public function findOneByCode(string $code): ?CarouselInterface
    {
        $obj = parent::findOneByCode($code);

        Assert::nullOrIsInstanceOf($obj, CarouselInterface::class);

        return $obj;
    }
}
