<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\RichText;

use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Tiptap\Core\Mark;

/**
 * Rich text's link for tiptap-php: a mark whose attributes are a link without its label, {target, ref, anchor}, with a
 * plain string ref for every target, since rich text is kept per locale. It renders as an <a> to where the link
 * resolves for the shopper, and as plain text when it doesn't resolve. Marked, as in the builder's preview, it also
 * carries its attributes, and one that doesn't resolve renders as a <span>, so typing in place reads every link back.
 * See "Rich text" in docs/spec/content-model.md
 *
 * @internal
 */
final class CMSLink extends Mark
{
    /** @var string */
    public static $name = 'cmsLink';

    /**
     * @param (\Closure(string, string, ?string): ?ResolvedLink)|null $resolve where a link's target, ref and anchor lead
     *                                                                        for the shopper. Without it, none resolves
     * @param bool $marked whether each link carries its attributes
     */
    public function __construct(
        private readonly ?\Closure $resolve = null,
        private readonly bool $marked = false,
    ) {
        parent::__construct();
    }

    /**
     * tiptap-php calls it with the mark to open its tag, and again to close it
     *
     * @param object $mark
     * @param array<string, mixed> $HTMLAttributes
     *
     * @return array{0: string, 1: array<string, string|null>, 2: 0}|null
     */
    public function renderHTML($mark, $HTMLAttributes = []): ?array
    {
        $attributes = property_exists($mark, 'attrs') && is_object($mark->attrs) ? get_object_vars($mark->attrs) : [];
        $target = $attributes['target'] ?? null;
        $ref = $attributes['ref'] ?? null;
        $anchor = $attributes['anchor'] ?? null;
        if (!is_string($target) || !is_string($ref)) {
            return null;
        }

        $anchor = is_string($anchor) ? $anchor : null;
        $link = null === $this->resolve ? null : ($this->resolve)($target, $ref, $anchor);
        $data = $this->marked ? ['data-cms-link-target' => $target, 'data-cms-link-ref' => $ref, 'data-cms-link-anchor' => $anchor] : [];

        if (null !== $link) {
            return ['a', ['href' => $link->url] + $data, 0];
        }

        return $this->marked ? ['span', $data, 0] : null;
    }
}
