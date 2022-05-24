<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader;

use Setono\SyliusCMSPlugin\Generator\Twig\TwigGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Repository\CarouselRepositoryInterface;
use Twig\Error\LoaderError;

final class CarouselLoader implements ElementLoaderInterface
{
    private CarouselRepositoryInterface $carouselRepository;

    /** @var TwigGeneratorInterface<CarouselInterface> */
    private TwigGeneratorInterface $twigGenerator;

    /**
     * @param TwigGeneratorInterface<CarouselInterface> $twigGenerator
     */
    public function __construct(CarouselRepositoryInterface $carouselRepository, TwigGeneratorInterface $twigGenerator)
    {
        $this->carouselRepository = $carouselRepository;
        $this->twigGenerator = $twigGenerator;
    }

    public function getSource(LogicalTemplateName $logicalTemplateName): string
    {
        // todo make more effective
        // todo should this take the channel and locale into consideration?
        $carousel = $this->carouselRepository->findOneByCode($logicalTemplateName->code);
        if (null === $carousel) {
            throw new LoaderError(sprintf('The carousel "%s" does not exist', (string) $logicalTemplateName)); // todo should another exception (from this plugin) be thrown instead and then handled in the composite loader?
        }

        return $this->twigGenerator->generate($carousel);
    }

    public function exists(LogicalTemplateName $logicalTemplateName): bool
    {
        // todo make more effective
        // todo should this take the channel and locale into consideration?
        return $this->carouselRepository->findOneByCode($logicalTemplateName->code) !== null;
    }

    public function isFresh(LogicalTemplateName $logicalTemplateName, int $time): bool
    {
        // todo make more effective
        // todo should this take the channel and locale into consideration?
        $carousel = $this->carouselRepository->findOneByCode($logicalTemplateName->code);
        if (null === $carousel) {
            throw new LoaderError(sprintf('The carousel "%s" does not exist', (string) $logicalTemplateName)); // todo should another exception (from this plugin) be thrown instead and then handled in the composite loader?
        }

        return true; // todo implement timestampable on the ElementInterface
    }

    public function supports(LogicalTemplateName $logicalTemplateName): bool
    {
        return ElementInterface::TYPE_CAROUSEL === $logicalTemplateName->type;
    }
}
