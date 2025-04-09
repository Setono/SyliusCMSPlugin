<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Sylius\Component\Resource\Model\TranslatableInterface;
use Twig\Environment;

/**
 * @template T of ElementInterface
 * @implements TwigGeneratorInterface<T>
 */
class GenericTwigGenerator implements TwigGeneratorInterface
{
    public function __construct(
        private readonly Environment $twig,
        /** @var class-string<ElementInterface> $supports */
        private readonly string $supports,
        private readonly string $template,
    ) {
    }

    /**
     * @param T $element
     * @param array<string, mixed> $context
     */
    public function generate(ElementInterface $element, string $channelCode, string $localeCode, array $context = []): string
    {
        if ($element instanceof TranslatableInterface) {
            $element->setCurrentLocale($localeCode);
        }

        return $this->twig->render($this->template, [
            'element' => $element,
        ]);
    }

    public function supports(ElementInterface $element, string $channelCode, string $localeCode, array $context = []): bool
    {
        return $element instanceof $this->supports;
    }
}
