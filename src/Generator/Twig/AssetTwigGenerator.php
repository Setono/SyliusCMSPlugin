<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements TwigGeneratorInterface<AssetInterface>
 */
final class AssetTwigGenerator implements TwigGeneratorInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * @param AssetInterface $element
     */
    public function generate(ElementInterface $element, array $context = []): string
    {
        if (!str_starts_with((string) $element->getMimeType(), 'image')) {
            return '';
        }

        return sprintf(
            '{%% import "@SetonoSyliusCMSPlugin/asset.html.twig" as asset %%}{{ asset.image("%s", "%s", "%s") }}{%% do sscms_push_to_element_stack(%d, "%s", "%s", "%s") %%}',
            $this->urlGenerator->generate('setono_sylius_cms_view_asset', ['id' => $element->getId()]),
            $element->getIdentifier(),
            (string) $element->getName(),
            (int) $element->getId(),
            (string) $element->getCode(),
            $element->getIdentifier(),
            $element->getType(),
        );
    }
}
