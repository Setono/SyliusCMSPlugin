<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Field\Link;
use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Value\Link as LinkValue;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * In the builder's preview, follows a block's output with a notice for each of its Link fields that doesn't resolve in
 * the preview's channel and locale, under its Shown to and Date, such as a link to an entry that isn't published or
 * translated, or to a product that was deleted, and for each rich text field holding such links, which show as plain
 * text. Shoppers don't see the link, so editors see why. The bridge finds the
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
            $message = match (true) {
                $field->field instanceof Link && self::linkDoesNotResolve(self::value($view->block, $name)) => 'setono_sylius_cms.preview.unresolved_link',
                $field->field instanceof RichText && self::holdsUnresolvedLinks(self::value($view->block, $name)) => 'setono_sylius_cms.preview.unresolved_rich_text_link',
                default => null,
            };
            if (null === $message) {
                continue;
            }

            $html .= sprintf(
                '<div data-cms-unresolved-link="%s" role="note" style="%s">%s</div>',
                htmlspecialchars($name, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
                self::STYLE,
                htmlspecialchars($this->translator->trans($message, ['%field%' => $this->translator->trans($field->field->label)]), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
            );
        }

        return $html;
    }

    /**
     * Whether a Link field's value is a link that doesn't resolve. A field without a link holds none
     */
    private static function linkDoesNotResolve(mixed $value): bool
    {
        return $value instanceof LinkValue && null === $value->resolved;
    }

    /**
     * Whether a rich text field's value holds links that don't resolve, which it shows as plain text
     */
    private static function holdsUnresolvedLinks(mixed $value): bool
    {
        return $value instanceof RichTextValue && $value->unresolvedLinks > 0;
    }

    /**
     * The block's hydrated value of the field, or null when it has none
     */
    private static function value(object $block, string $name): mixed
    {
        if (!property_exists($block, $name)) {
            return null;
        }

        $property = new \ReflectionProperty($block, $name);

        return $property->isInitialized($block) ? $property->getValue($block) : null;
    }
}
