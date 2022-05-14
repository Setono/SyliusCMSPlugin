<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Twig;

use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Webmozart\Assert\Assert;

/**
 * @implements TwigGeneratorInterface<BlockInterface>
 */
final class BlockTwigGenerator implements TwigGeneratorInterface
{
    /**
     * @param BlockInterface $element
     */
    public function generate(ElementInterface $element, array $context = []): string
    {
        Assert::keyExists($context, 'localeCode');
        Assert::string($context['localeCode']);

        $element->setCurrentLocale($context['localeCode']);

        return sprintf(
            '{%% set identifier = "%s" %%}{%% extends "@SetonoSyliusCMSPlugin/block.html.twig" %%}{%% block content %%}%s{%% endblock %%}{%% do sscms_push_to_element_stack(%d, "%s", "%s", "%s") %%}',
            $element->getIdentifier(),
            (string) $element->getContent(),
            (int) $element->getId(),
            (string) $element->getCode(),
            $element->getIdentifier(),
            $element->getType()
        );
    }
}
