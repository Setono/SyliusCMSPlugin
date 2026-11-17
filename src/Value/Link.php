<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Value;

use Setono\SyliusCMSPlugin\Link\ResolvedLink;

/**
 * A Link field's value in the current locale, with the ref and the label already picked along the fallback chain.
 * cms_link() gives where it leads for the shopper, or null when it doesn't resolve and isn't rendered. See "Links" in
 * docs/spec/content-model.md
 */
final readonly class Link
{
    /**
     * @param string $target the link target's code
     * @param string $ref what it refers to in the current locale: a code, a UUID or a URL
     * @param string|null $anchor a fragment without the #
     * @param string|null $label the editor's label in the current locale, or null to show the target's current name
     * @param ResolvedLink|null $resolved where it leads, with the anchor, or null when it doesn't resolve: the reference
     *                                    is broken, or the shopper may not see what it refers to
     */
    public function __construct(
        public string $target,
        public string $ref,
        public ?string $anchor,
        public ?string $label,
        public ?ResolvedLink $resolved,
    ) {
    }
}
