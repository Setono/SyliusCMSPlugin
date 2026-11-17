<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Link;

/**
 * Where a link leads for the shopper. It keeps the target and the reference it resolved beside the URL, so a later API
 * can give a headless frontend both. See "Link targets" in docs/spec/extending.md and "Kept for a later API" in
 * docs/spec/rendering.md
 */
final readonly class ResolvedLink
{
    /**
     * @param string $target the link target's code
     * @param string $ref the reference it resolved: a code, a UUID or a URL
     * @param string $name the current name of what it leads to in the shopper's locale, which an empty label shows
     */
    public function __construct(
        public string $target,
        public string $ref,
        public string $url,
        public string $name,
    ) {
    }

    /**
     * The same link to the anchor, a fragment without the #, in place of a fragment the URL has. Null or '' leaves it
     */
    public function withAnchor(?string $anchor): self
    {
        if (null === $anchor || '' === $anchor) {
            return $this;
        }

        return new self($this->target, $this->ref, sprintf('%s#%s', explode('#', $this->url, 2)[0], rawurlencode($anchor)), $this->name);
    }
}
