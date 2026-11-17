<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Field\Link;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Value\Link as LinkValue;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * In the builder's preview, follows a block's output with a notice for each of its Link fields that doesn't resolve in
 * the preview's channel and locale, under its Shown to and Date, such as a link to an entry that isn't published or
 * translated, or to a product that was deleted. Shoppers don't see the link, so editors see why. The bridge finds the
 * notices by their data-cms-unresolved-link attribute and flags their blocks in the tree. Shoppers and share links get
 * none. See "Left pane: the block tree" in docs/spec/builder.md and "References to unavailable entries" in
 * docs/spec/localization.md
 */
final readonly class UnresolvedLinkNotices implements BlockRendererInterface
{
    private const STYLE = 'box-sizing:border-box;margin:0.5rem 0;padding:0.5rem 0.75rem;border:2px dashed #b35c00;border-radius:4px;background:#fff8e6;color:#7a3e00;font:13px/1.4 system-ui,sans-serif';

    public function __construct(
        private BlockRendererInterface $renderer,
        private BlockTypeRegistryInterface $blockTypes,
        private TranslatorInterface $translator,
    ) {
    }

    public function render(BlockView $view, RenderContext $context): string
    {
        $html = $this->renderer->render($view, $context);
        if (RenderContext::PREVIEW_MODE_BUILDER !== $context->previewMode || !$this->blockTypes->has($view->type)) {
            return $html;
        }

        foreach ($this->blockTypes->get($view->type)->fields as $name => $field) {
            if (!$field->field instanceof Link || !self::doesNotResolve($view->block, $name)) {
                continue;
            }

            $html .= sprintf(
                '<div data-cms-unresolved-link="%s" role="note" style="%s">%s</div>',
                htmlspecialchars($name, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
                self::STYLE,
                htmlspecialchars($this->translator->trans('setono_sylius_cms.preview.unresolved_link', ['%field%' => $this->translator->trans($field->field->label)]), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
            );
        }

        return $html;
    }

    /**
     * Whether the block holds a link in the field that doesn't resolve. A field without a link holds none
     */
    private static function doesNotResolve(object $block, string $name): bool
    {
        if (!property_exists($block, $name)) {
            return false;
        }

        $property = new \ReflectionProperty($block, $name);
        $link = $property->isInitialized($block) ? $property->getValue($block) : null;

        return $link instanceof LinkValue && null === $link->resolved;
    }
}
