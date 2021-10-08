<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Model\CMSView;

use Setono\SyliusCMSPlugin\Model\ViewBlockInterface;

/** @internal */
interface CMSBlockModelFactoryInterface
{
    public function createNewFromViewBlock(ViewBlockInterface $viewBlock): CMSBlockModel;
}
