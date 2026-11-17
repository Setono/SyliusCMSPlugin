<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Link\LinkTargetInterface;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Value\Link;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * cms_link() and cms_url(). See "Twig" in docs/spec/extending.md
 */
final readonly class LinkRuntime implements RuntimeExtensionInterface
{
    /**
     * @param LinkTargetInterface $entryLinkTarget the entry link target, which knows the entries the shopper may see
     */
    public function __construct(
        private LinkTargetInterface $entryLinkTarget,
        private RenderContexts $renderContexts,
    ) {
    }

    /**
     * Where a Link value leads for the shopper, with its anchor, and the target's name an empty label shows. Null means
     * don't render the link: it has none, its reference is broken, or the shopper may not see what it refers to
     */
    public function link(?Link $link): ?ResolvedLink
    {
        return $link?->resolved;
    }

    /**
     * An entry's URL in the current channel and locale, the channel's root for its homepage. Null when it has none, or
     * the shopper may not see it there. The channel and locale are the render context's in a CMS template, and the
     * shop's anywhere else
     *
     * @param array<string, mixed> $context
     */
    public function url(array $context, ?EntryInterface $entry): ?string
    {
        if (null === $entry) {
            return null;
        }

        return $this->entryLinkTarget->resolve($entry->getUuid(), $this->renderContexts->of($context))?->url;
    }
}
