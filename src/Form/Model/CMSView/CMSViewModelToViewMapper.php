<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Model\CMSView;

use Setono\SyliusCMSPlugin\Model\ViewBlockInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/** @internal */
final class CMSViewModelToViewMapper implements CMSViewModelToViewMapperInterface
{
    private FactoryInterface $viewBlockFactory;

    public function __construct(FactoryInterface $viewBlockFactory)
    {
        $this->viewBlockFactory = $viewBlockFactory;
    }

    public function map(ViewInterface $view, CMSViewModel $viewModel): void
    {
        $view->setCode($viewModel->getCode());
        $view->setTemplate($viewModel->template);

        // Remove existing view blocks. TODO: See if we can merge in a clever way instead
        $existingViewBlocks = $view->getViewBlocks();
        foreach ($existingViewBlocks as $existingViewBlock) {
            $view->removeViewBlock($existingViewBlock);
        }

        foreach ($viewModel->sections as $sectionModel) {
            $sectionName = $sectionModel->name;

            foreach ($sectionModel->blocks as $position => $blockModel) {
                /** @var ViewBlockInterface $viewBlock */
                $viewBlock = $this->viewBlockFactory->createNew();

                $viewBlock->setSection($sectionName);
                $viewBlock->setBlock($blockModel->block);
                $viewBlock->setPriority($position);

                $view->addViewBlock($viewBlock);
            }
        }
    }
}
