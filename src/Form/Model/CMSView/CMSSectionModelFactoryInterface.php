<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Model\CMSView;

use Setono\SyliusCMSPlugin\Model\ViewBlockInterface;

/** @internal */
interface CMSSectionModelFactoryInterface
{
    /**
     * @psalm-param iterable<array-key, ViewBlockInterface> $blocks
     */
    public function createFromNameAndBlocks(string $name, iterable $blocks): CMSSectionModel;
}
