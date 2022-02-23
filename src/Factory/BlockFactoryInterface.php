<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

interface BlockFactoryInterface extends FactoryInterface
{
    /** @param string|int|BlockInterface $resource */
    public function createNewFromResource($resource): BlockInterface;
}
