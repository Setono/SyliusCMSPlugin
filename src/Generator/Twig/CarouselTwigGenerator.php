<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\SyliusCMSPlugin\Model\CarouselBlockInterface;
use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Webmozart\Assert\Assert;

/**
 * @implements TwigGeneratorInterface<CarouselInterface>
 */
final class CarouselTwigGenerator implements TwigGeneratorInterface
{
    /**
     * @param CarouselInterface $element
     */
    public function generate(ElementInterface $element, array $context = []): string
    {
        Assert::isInstanceOf($element, CarouselInterface::class);

        /** @var array<int, string> $items */
        $items = [];

        /** @var CarouselBlockInterface $carouselBlock */
        foreach ($element->getCarouselBlocks() as $carouselBlock) {
            $block = $carouselBlock->getBlock();
            if (null === $block) {
                continue;
            }

            $items[$carouselBlock->getPosition()] = (string) $block->getCode();
        }

        ksort($items);

        $scope = [
            'identifier' => $element->getIdentifier(),
            'items' => $items,
            'configuration' => $element->getConfiguration(),
        ];

        return sprintf(
            '{%% with %s %%}{%% use "@SetonoSyliusCMSPlugin/carousel.html.twig" %%}{{ block("carousel") }}{%% endwith %%}{%% do sscms_push_to_element_stack(%d, "%s", "%s", "%s") %%}',
            json_encode($scope),
            (int) $element->getId(),
            (string) $element->getCode(),
            $element->getIdentifier(),
            $element->getType(),
        );
    }
}
