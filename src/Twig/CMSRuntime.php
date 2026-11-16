<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Preview\FieldMarker;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Rendering\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Value\RichText;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class CMSRuntime implements RuntimeExtensionInterface
{
    /**
     * The template variable that carries the RenderContext into layout and block templates,
     * so cms_block() and cms_blocks() render with the context the page was rendered with
     */
    public const RENDER_CONTEXT = '_cms_render_context';

    public function __construct(
        private BlockRendererInterface $blockRenderer,
        private FieldMarker $fieldMarker,
        private FallbackValues $fallbackValues,
        private PreviewContext $previewContext,
    ) {
    }

    /**
     * The mode of the preview the page is rendered in, builder or share, or null outside a preview
     */
    public function previewMode(): ?string
    {
        return $this->previewContext->getMode();
    }

    /**
     * Prints a field of a block, a layout or an entry. A block's or a layout's value is already hydrated, with fallback
     * resolved, and rich text is printed as its HTML. An entry's translatable field falls back through the fallback chain
     * of the render context's locale. In the builder's preview, a field of a block being rendered is marked, so editors
     * can type it in place
     *
     * @param array<string, mixed> $context
     */
    public function field(array $context, object $subject, string $name): string
    {
        $renderContext = $context[self::RENDER_CONTEXT] ?? null;

        if ($subject instanceof EntryInterface) {
            if (!$renderContext instanceof RenderContext) {
                throw new \LogicException(sprintf('An entry\'s fields can only be printed from a template that has the render context in "%s", such as a layout template', self::RENDER_CONTEXT));
            }

            return self::escape($subject, $name, $this->fallbackValues->entryValue($subject, $name, $renderContext->localeCode));
        }

        $html = self::escape($subject, $name, self::read($subject, $name));

        $view = self::viewOf($context['view'] ?? null, $subject);
        if (!$renderContext instanceof RenderContext || null === $view) {
            return $html;
        }

        return $this->fieldMarker->mark($html, $view, $name, $renderContext);
    }

    /**
     * Reads a field of a block, a layout or an entry without printing it, such as a media list's ItemList to loop over or
     * a media field's media item. A block's or a layout's value is already hydrated, with fallback resolved, and null
     * when it's missing, such as a broken reference. An entry's translatable field falls back through the fallback
     * chain of the render context's locale. It's never marked for typing in place
     *
     * @param array<string, mixed> $context
     */
    public function value(array $context, object $subject, string $name): mixed
    {
        if (!$subject instanceof EntryInterface) {
            return self::read($subject, $name);
        }

        $renderContext = $context[self::RENDER_CONTEXT] ?? null;
        if (!$renderContext instanceof RenderContext) {
            throw new \LogicException(sprintf('An entry\'s fields can only be read from a template that has the render context in "%s", such as a layout template', self::RENDER_CONTEXT));
        }

        return $this->fallbackValues->entryValue($subject, $name, $renderContext->localeCode);
    }

    /**
     * The value of a field of a block or a layout: its public property, which holds the hydrated value. One that was
     * never set is missing
     */
    private static function read(object $subject, string $name): mixed
    {
        if (!property_exists($subject, $name)) {
            throw new \InvalidArgumentException(sprintf('%s has no field "%s"', $subject::class, $name));
        }

        $property = new \ReflectionProperty($subject, $name);
        if (!$property->isPublic()) {
            throw new \InvalidArgumentException(sprintf('The field "%s" of %s must be a public property', $name, $subject::class));
        }

        return $property->isInitialized($subject) ? $property->getValue($subject) : null;
    }

    private static function escape(object $subject, string $name, mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        // Rendered from the sanitised document by tiptap-php, which escapes the text
        if ($value instanceof RichText) {
            return $value->html;
        }

        if (!is_scalar($value) && !$value instanceof \Stringable) {
            throw new \InvalidArgumentException(sprintf('The field "%s" of %s holds %s, which cms_field() can\'t print. Use cms_value() to read it', $name, $subject::class, get_debug_type($value)));
        }

        return htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $context
     */
    public function block(array $context, BlockView $view): string
    {
        return $this->blockRenderer->render($view, self::renderContext($context));
    }

    /**
     * @param array<string, mixed> $context
     * @param iterable<BlockView> $views
     */
    public function blocks(array $context, iterable $views): string
    {
        $renderContext = self::renderContext($context);

        $html = '';
        foreach ($views as $view) {
            $html .= $this->blockRenderer->render($view, $renderContext);
        }

        return $html;
    }

    /**
     * The view of the block the subject is: the view of the template's block, or of one of the blocks in it, whose
     * fields a template may print too
     */
    private static function viewOf(mixed $view, object $subject): ?BlockView
    {
        if (!$view instanceof BlockView) {
            return null;
        }

        if ($view->block === $subject) {
            return $view;
        }

        foreach ($view->children as $child) {
            $found = self::viewOf($child, $subject);
            if (null !== $found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function renderContext(array $context): RenderContext
    {
        $renderContext = $context[self::RENDER_CONTEXT] ?? null;
        if (!$renderContext instanceof RenderContext) {
            throw new \LogicException(sprintf('Blocks can only be rendered from a template that has the render context in "%s", such as a layout or block template', self::RENDER_CONTEXT));
        }

        return $renderContext;
    }
}
