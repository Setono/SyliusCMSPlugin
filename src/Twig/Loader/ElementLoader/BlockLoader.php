<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Twig\Error\LoaderError;

final class BlockLoader implements ElementLoaderInterface
{
    private BlockRepositoryInterface $blockRepository;

    public function __construct(BlockRepositoryInterface $blockRepository)
    {
        $this->blockRepository = $blockRepository;
    }

    public function getSource(LogicalTemplateName $logicalTemplateName): string
    {
        return '{{ sylius.channel.code }}'; // todo fix
    }

    public function exists(LogicalTemplateName $logicalTemplateName): bool
    {
        // todo make more effective
        return $this->blockRepository->findOneByCode($logicalTemplateName->code) !== null;
    }

    public function isFresh(LogicalTemplateName $logicalTemplateName, int $time): bool
    {
        // todo make more effective
        $block = $this->blockRepository->findOneByCode($logicalTemplateName->code);
        if(null === $block) {
            throw new LoaderError(sprintf('The block "%s" does not exist', (string) $logicalTemplateName)); // todo should another exception (from this plugin) be thrown instead and then handled in the composite loader?
        }

        return true; // todo implement timestampable on the ElementInterface
    }

    public function supports(LogicalTemplateName $logicalTemplateName): bool
    {
        return ElementInterface::TYPE_BLOCK === $logicalTemplateName->type;
    }
}
