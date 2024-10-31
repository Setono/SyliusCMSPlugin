<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Twig\Environment;

/**
 * @template T of ElementInterface
 * @implements TwigGeneratorInterface<T>
 */
abstract class AbstractTwigGenerator implements TwigGeneratorInterface
{
    public function __construct(private readonly Environment $twig, private readonly string $supportsType)
    {
    }

    /**
     * @param T $element
     * @param array<string, mixed> $context
     */
    public function generate(ElementInterface $element, array $context = []): string
    {
        return $this->twig->render(sprintf('@SetonoSyliusCMSPlugin/twig_generator/%s.html.twig', $element->getType()), [
            'element' => $element,
        ]);
    }

    public function supports(ElementInterface $element, array $context = []): bool
    {
        return $element->getType() === $this->supportsType;
    }
}
