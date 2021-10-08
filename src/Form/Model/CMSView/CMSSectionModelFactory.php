<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Model\CMSView;

/** @internal */
final class CMSSectionModelFactory implements CMSSectionModelFactoryInterface
{
    /** @psalm-var class-string<CMSSectionModel> */
    private string $sectionModelClass;

    private CMSBlockModelFactoryInterface $blockModelFactory;

    /**
     * @psalm-param class-string<CMSSectionModel> $sectionModelClass
     */
    public function __construct(string $sectionModelClass, CMSBlockModelFactoryInterface $blockModelFactory)
    {
        $this->sectionModelClass = $sectionModelClass;
        $this->blockModelFactory = $blockModelFactory;
    }

    public function createFromNameAndBlocks(string $name, iterable $blocks): CMSSectionModel
    {
        /** @psalm-suppress UnsafeInstantiation */
        $sectionModel = new $this->sectionModelClass();
        $sectionModel->name = $name;
        foreach ($blocks as $block) {
            $sectionModel->blocks[] = $this->blockModelFactory->createNewFromViewBlock($block);
        }

        return $sectionModel;
    }
}
