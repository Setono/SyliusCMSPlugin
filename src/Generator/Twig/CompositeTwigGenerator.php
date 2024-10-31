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
    public function generate(ElementInterface $element, array $context = []): string
    {
        foreach ($this->services as $service) {
            if ($service->supports($element, $context)) {
                return $service->generate($element, $context);
            }
        }

        throw new \RuntimeException(sprintf(
            'No twig generator found for element with type %s',
            $element->getType(),
        ));
    }

    public function supports(ElementInterface $element, array $context = []): bool
    {
        foreach ($this->services as $service) {
            if ($service->supports($element, $context)) {
                return true;
            }
        }

        return false;
    }
}
