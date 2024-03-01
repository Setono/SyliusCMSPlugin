<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;

/**
 * @extends  AbstractTwigGenerator<AssetInterface>
 */
final class AssetTwigGenerator extends AbstractTwigGenerator
{
    /**
     * @param AssetInterface $element
     * @param array<string, mixed> $context
     */
    public function generate(ElementInterface $element, array $context = []): string
    {
        if (!str_starts_with((string) $element->getMimeType(), 'image')) {
            return '';
        }

        return parent::generate($element, $context);
    }
}
