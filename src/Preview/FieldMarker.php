<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * In the builder's preview, brackets what cms_field() prints for a block's text, textarea or rich text field with
 * HTML comment markers that carry the block's key, the field's name and its field type, so the bridge can make it
 * editable: typing in place. Shoppers and share links get the field as it is. See "Working in the preview" and
 * "The bridge" in docs/spec/builder.md
 *
 * The marker format is internal: only the bridge reads it (assets/builder/src/bridge/markers.ts)
 *
 * @internal
 */
final readonly class FieldMarker
{
    /** The field types editors can type in place */
    private const FIELD_TYPES = ['text', 'textarea', 'rich_text'];

    public function __construct(private BlockTypeRegistryInterface $blockTypes)
    {
    }

    /**
     * @param string $html what cms_field() prints for the field
     */
    public function mark(string $html, BlockView $view, string $field, RenderContext $context): string
    {
        if (RenderContext::PREVIEW_MODE_BUILDER !== $context->previewMode) {
            return $html;
        }

        $type = $this->blockTypes->get($view->type)->fields[$field]->type->code ?? null;
        if (!in_array($type, self::FIELD_TYPES, true)) {
            return $html;
        }

        return sprintf('<!--cms:field %s %s %s-->%s<!--/cms:field %1$s %2$s-->', $view->key, $field, $type, $html);
    }
}
