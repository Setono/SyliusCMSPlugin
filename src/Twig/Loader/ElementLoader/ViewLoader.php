<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader;

use Setono\SyliusCMSPlugin\Generator\Twig\TwigGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Twig\Error\LoaderError;

final class ViewLoader implements ElementLoaderInterface
{
    private ViewRepositoryInterface $viewRepository;

    /** @var TwigGeneratorInterface<ViewInterface> */
    private TwigGeneratorInterface $twigGenerator;

    /**
     * @param TwigGeneratorInterface<ViewInterface> $twigGenerator
     */
    public function __construct(ViewRepositoryInterface $viewRepository, TwigGeneratorInterface $twigGenerator)
    {
        $this->viewRepository = $viewRepository;
        $this->twigGenerator = $twigGenerator;
    }

    public function getSource(LogicalTemplateName $logicalTemplateName): string
    {
        // todo make more effective
        // todo should this take the channel and locale into consideration?
        $view = $this->viewRepository->findOneByCode($logicalTemplateName->code);
        if (null === $view) {
            throw new LoaderError(sprintf('The view "%s" does not exist', (string) $logicalTemplateName)); // todo should another exception (from this plugin) be thrown instead and then handled in the composite loader?
        }

        return $this->twigGenerator->generate($view);
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
