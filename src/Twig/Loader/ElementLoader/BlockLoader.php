<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader;

use Setono\SyliusCMSPlugin\Generator\Twig\StringBasedTwigGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Twig\Error\LoaderError;

final class BlockLoader implements ElementLoaderInterface
{
    private BlockRepositoryInterface $blockRepository;

    private StringBasedTwigGeneratorInterface $twigGenerator;

    public function __construct(
        BlockRepositoryInterface $blockRepository,
        StringBasedTwigGeneratorInterface $twigGenerator
    ) {
        $this->blockRepository = $blockRepository;
        $this->twigGenerator = $twigGenerator;
    }

    public function getSource(LogicalTemplateName $logicalTemplateName): string
    {
        // todo make more effective
        // todo should this take the channel and locale into consideration?
        $block = $this->blockRepository->findOneByCode($logicalTemplateName->code);
        if (null === $block) {
            throw new LoaderError(sprintf(
                'The block "%s" does not exist',
                (string) $logicalTemplateName
            )); // todo should another exception (from this plugin) be thrown instead and then handled in the composite loader?
        }

        $block->setCurrentLocale($logicalTemplateName->localeCode);

        return $this->twigGenerator->generate($block->getContent());
    }

    public function exists(LogicalTemplateName $logicalTemplateName): bool
    {
        // todo make more effective
        // todo should this take the channel and locale into consideration?
        return $this->blockRepository->findOneByCode($logicalTemplateName->code) !== null;
    }

    public function isFresh(LogicalTemplateName $logicalTemplateName, int $time): bool
    {
        // todo make more effective
        // todo should this take the channel and locale into consideration?
        $block = $this->blockRepository->findOneByCode($logicalTemplateName->code);
        if (null === $block) {
            throw new LoaderError(sprintf(
                'The block "%s" does not exist',
                (string) $logicalTemplateName
            )); // todo should another exception (from this plugin) be thrown instead and then handled in the composite loader?
        }

        return true; // todo implement timestampable on the ElementInterface
    }

    public function supports(LogicalTemplateName $logicalTemplateName): bool
    {
        return ElementInterface::TYPE_BLOCK === $logicalTemplateName->type;
    }
}
