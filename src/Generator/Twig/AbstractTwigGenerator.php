<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Twig\Environment;

/**
 * @template T of ElementInterface
 *
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
        $twig = $this->twig->render(sprintf('@SetonoSyliusCMSPlugin/twig_generator/%s.html.twig', $element->getType()), [
            'element' => $element,
        ]);
        $twig .= sprintf(
            '{%% do sscms_push_to_element_stack(%d, "%s", "%s", "%s") %%}',
            (int) $element->getId(),
            (string) $element->getCode(),
            $element->getIdentifier(),
            $element->getType(),
        );

        return $twig;
    }

    public function supports(ElementInterface $element, array $context = []): bool
    {
        return $element->getType() === $this->supportsType;
    }
}
