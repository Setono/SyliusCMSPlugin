<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Model\CMSView;

use Setono\SyliusCMSPlugin\Model\ViewInterface;

/** @internal */
interface CMSViewModelToViewMapperInterface
{
    public function map(ViewInterface $view, CMSViewModel $viewModel): void;
}
