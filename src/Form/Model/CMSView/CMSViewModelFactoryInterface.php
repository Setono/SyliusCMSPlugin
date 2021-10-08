<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Model\CMSView;

use Setono\SyliusCMSPlugin\Model\ViewInterface;

/** @internal */
interface CMSViewModelFactoryInterface
{
    public function createFromView(ViewInterface $view): CMSViewModel;
}
