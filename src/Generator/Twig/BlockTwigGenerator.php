<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Webmozart\Assert\Assert;

/**
 * @extends AbstractTwigGenerator<BlockInterface>
 */
final class BlockTwigGenerator extends AbstractTwigGenerator
{
    /**
     * @param BlockInterface $element
     * @param array<string, mixed> $context
     */
    public function generate(ElementInterface $element, array $context = []): string
    {
        Assert::keyExists($context, 'localeCode');
        Assert::string($context['localeCode']);

        $element->setCurrentLocale($context['localeCode']);

        return parent::generate($element, $context);
    }
}
