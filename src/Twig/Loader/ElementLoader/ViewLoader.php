<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Setono\SyliusCMSPlugin\Transpiler\ElementToTwigTranspilerInterface;
use Twig\Error\LoaderError;

final class ViewLoader implements ElementLoaderInterface
{
    private ViewRepositoryInterface $viewRepository;

    private ElementToTwigTranspilerInterface $elementToTwigTranspiler;

    public function __construct(ViewRepositoryInterface $viewRepository, ElementToTwigTranspilerInterface $elementToTwigTranspiler)
    {
        $this->viewRepository = $viewRepository;
        $this->elementToTwigTranspiler = $elementToTwigTranspiler;
    }

    public function getSource(LogicalTemplateName $logicalTemplateName): string
    {
        // todo make more effective
        // todo should this take the channel and locale into consideration?
        $view = $this->viewRepository->findOneByCode($logicalTemplateName->code);
        if (null === $view) {
            throw new LoaderError(sprintf('The view "%s" does not exist', (string) $logicalTemplateName)); // todo should another exception (from this plugin) be thrown instead and then handled in the composite loader?
        }

        return $this->elementToTwigTranspiler->transpile($view);
    }

    public function exists(LogicalTemplateName $logicalTemplateName): bool
    {
        // todo make more effective
        // todo should this take the channel and locale into consideration?
        return $this->viewRepository->findOneByCode($logicalTemplateName->code) !== null;
    }

    public function isFresh(LogicalTemplateName $logicalTemplateName, int $time): bool
    {
        // todo make more effective
        // todo should this take the channel and locale into consideration?
        $view = $this->viewRepository->findOneByCode($logicalTemplateName->code);
        if (null === $view) {
            throw new LoaderError(sprintf('The view "%s" does not exist', (string) $logicalTemplateName)); // todo should another exception (from this plugin) be thrown instead and then handled in the composite loader?
        }

        return true; // todo implement timestampable on the ElementInterface
    }

    public function supports(LogicalTemplateName $logicalTemplateName): bool
    {
        return ElementInterface::TYPE_VIEW === $logicalTemplateName->type;
    }
}
