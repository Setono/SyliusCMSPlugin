<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\CompositeCompilerPass\CompositeService;
use Setono\SyliusCMSPlugin\Model\ElementInterface;

/**
 * @extends CompositeService<TwigGeneratorInterface>
 * @implements TwigGeneratorInterface<ElementInterface>
 */
final class CompositeTwigGenerator extends CompositeService implements TwigGeneratorInterface
{
    public function generate(ElementInterface $element, string $channelCode, string $localeCode, array $context = []): string
    {
        foreach ($this->services as $service) {
            if ($service->supports($element, $channelCode, $localeCode, $context)) {
                return $service->generate($element, $channelCode, $localeCode, $context);
            }
        }

        throw new \RuntimeException(sprintf('No twig generator found for element %s', $element::class));
    }

    public function supports(ElementInterface $element, string $channelCode, string $localeCode, array $context = []): bool
    {
        foreach ($this->services as $service) {
            if ($service->supports($element, $channelCode, $localeCode, $context)) {
                return true;
            }
        }

        return false;
    }
}
