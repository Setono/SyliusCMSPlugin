<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Model\CMSView;

use Setono\SyliusCMSPlugin\Model\ViewBlockInterface;

/** @internal */
final class CMSBlockModelFactory implements CMSBlockModelFactoryInterface
{
    /** @psalm-var class-string<CMSBlockModel> */
    private string $blockModelClass;

    /**
     * @psalm-param class-string<CMSBlockModel> $blockModelClass
     */
    public function __construct(string $blockModelClass)
    {
        $this->blockModelClass = $blockModelClass;
    }

    public function createNewFromViewBlock(ViewBlockInterface $viewBlock): CMSBlockModel
    {
        /** @psalm-suppress UnsafeInstantiation */
        $blockModel = new $this->blockModelClass();

        $blockModel->block = $viewBlock->getBlock();

        return $blockModel;
    }
}
