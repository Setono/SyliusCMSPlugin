<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transpiler;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Twig\Extractor\SectionExtractorInterface;
use Webmozart\Assert\Assert;

/**
 * @implements ElementToTwigTranspilerInterface<ViewInterface>
 */
final class ViewToTwigTranspiler implements ElementToTwigTranspilerInterface
{
    /**
     * @param ViewInterface $element
     */
    public function transpile(ElementInterface $element): string
    {
        Assert::isInstanceOf($element, ViewInterface::class);

        $template = $element->getTemplate();
        Assert::notNull($template);

        $twig = '{% extends "@SetonoSyliusCMSPlugin/view.html.twig" %}';

        $twig .= sprintf('{%% block identifier %%}%s{%% endblock %%}', $element->getIdentifier());

        /** @var array<string, array<int, string>> $blocks */
        $blocks = [];

        foreach ($element->getViewBlocks() as $viewBlock) {
            $block = $viewBlock->getBlock();
            Assert::notNull($block);

            $blocks[(string) $viewBlock->getSection()][$viewBlock->getPosition()] = $block->getCode(); // todo notice that if somehow two blocks end up at the same position, this will effectively only show one of them, so this needs to be checked when saving a view or fixed some other way
        }

        $twig .= sprintf('{%% block content %%}{%% embed "%s" %%}', $template);
        foreach ($blocks as $section => $blockCodes) {
            ksort($blockCodes);

            $twig .= sprintf('{%% block %s %%}', SectionExtractorInterface::SECTION_PREFIX . $section);
            foreach ($blockCodes as $blockCode) {
                $twig .= sprintf("{{ sscms_block('%s') }}", $blockCode);
            }
            $twig .= '{% endblock %}';
        }

        $twig .= '{% endembed %}{% endblock %}';

        return $twig;
    }
}
