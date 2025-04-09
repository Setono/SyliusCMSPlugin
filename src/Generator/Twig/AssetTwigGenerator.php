<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;

/**
 * @extends  GenericTwigGenerator<AssetInterface>
 */
final class AssetTwigGenerator extends GenericTwigGenerator
{
    /**
     * @param AssetInterface $element
     * @param array<string, mixed> $context
     */
    public function generate(ElementInterface $element, string $channelCode, string $localeCode, array $context = []): string
    {
        if (!str_starts_with((string) $element->getMimeType(), 'image')) {
            return '';
        }

        return parent::generate($element, $channelCode, $localeCode, $context);
    }

    public function supports(ElementInterface $element, string $channelCode, string $localeCode, array $context = []): bool
    {
        return $element instanceof AssetInterface;
    }
}
