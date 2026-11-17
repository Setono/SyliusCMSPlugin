<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\RichText;

use Setono\SyliusCMSPlugin\Binding\StoredBinding;
use Tiptap\Core\Node;

/**
 * Rich text's binding token for tiptap-php: an inline atom node whose attributes are a binding, {property, params,
 * fallback}. It renders as the text of what the binding resolves to, or of its fallback when that's empty, and as
 * nothing without one. Marked, as in the builder's preview, it's a <span> that can't be typed in and carries its
 * attributes, so typing in place reads the token back. See "Rich text" in docs/spec/content-model.md
 *
 * @internal
 */
final class CMSBinding extends Node
{
    public const NAME = 'cmsBinding';

    /** @var mixed as tiptap-php types it: the name, which the schema matches nodes and marks by */
    public static $name = self::NAME;

    /**
     * @param (\Closure(StoredBinding): ?string)|null $resolve what a token's binding resolves to for the shopper, or null
     *                                                      when it's empty. Without it, every token shows its fallback
     * @param bool $marked whether each token carries its attributes
     */
    public function __construct(
        private readonly ?\Closure $resolve = null,
        private readonly bool $marked = false,
    ) {
        parent::__construct();
    }

    /**
     * tiptap-php calls it with the node to open its tag, and again to close it
     *
     * @param mixed $node tiptap-php's node, an object with its attrs
     * @param array<string, mixed> $HTMLAttributes
     *
     * @return array{content: string}
     */
    public function renderHTML($node, $HTMLAttributes = []): array
    {
        $attributes = is_object($node) && property_exists($node, 'attrs') && is_object($node->attrs) ? get_object_vars($node->attrs) : [];
        $property = $attributes['property'] ?? null;
        $fallback = is_string($attributes['fallback'] ?? null) ? $attributes['fallback'] : null;
        /** @var array<string, mixed> $params tiptap-php decodes the document into objects */
        $params = json_decode((string) json_encode($attributes['params'] ?? []), true);
        $params = is_array($params) ? $params : [];

        $value = is_string($property) && null !== $this->resolve ? ($this->resolve)(new StoredBinding($property, $params, $fallback)) : null;
        $text = htmlspecialchars($value ?? $fallback ?? '', \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        if (!$this->marked || !is_string($property)) {
            return ['content' => $text];
        }

        return ['content' => sprintf(
            '<span data-cms-binding="%s" data-cms-binding-params="%s"%s contenteditable="false">%s</span>',
            htmlspecialchars($property, \ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) json_encode((object) $params), \ENT_QUOTES, 'UTF-8'),
            null === $fallback ? '' : sprintf(' data-cms-binding-fallback="%s"', htmlspecialchars($fallback, \ENT_QUOTES, 'UTF-8')),
            $text,
        )];
    }
}
