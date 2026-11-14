<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Rendering\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class CmsRuntime implements RuntimeExtensionInterface
{
    /**
     * The template variable that carries the RenderContext into layout and block templates,
     * so cms_block() and cms_blocks() render with the context the page was rendered with
     */
    public const RENDER_CONTEXT = '_cms_render_context';

    public function __construct(private BlockRendererInterface $blockRenderer)
    {
    }

    /**
     * Prints a field of a block or a layout. Its value is already hydrated, with fallback resolved
     */
    public function field(object $subject, string $name): string
    {
        if (!property_exists($subject, $name)) {
            throw new \InvalidArgumentException(sprintf('%s has no field "%s"', $subject::class, $name));
        }

        $property = new \ReflectionProperty($subject, $name);
        if (!$property->isPublic()) {
            throw new \InvalidArgumentException(sprintf('The field "%s" of %s must be a public property', $name, $subject::class));
        }

        $value = $property->getValue($subject);

        if (null === $value) {
            return '';
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
