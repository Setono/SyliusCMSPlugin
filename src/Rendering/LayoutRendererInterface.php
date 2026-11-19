<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Collection\PageNotFoundException;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Renders an entry with its own URL in its layout, as the page shoppers see at its URL. See "Rendering an entry at its
 * own URL" in docs/spec/rendering.md
 */
interface LayoutRendererInterface
{
    /**
     * Renders the entry's tree, read through the upgraders, in the layout its tree names, or in its content type's
     * default layout when that one was removed from code or disabled. The layout template gets `entry`, `layout` (the
     * hydrated layout object), `blocks` (the top-level blocks, as BlockViews) and `context` (the host-page context).
     * Before the template renders, it checks that the render context's page of the entry's paginated Entries block exists
     *
     * @throws PageNotFoundException when the entry doesn't have that page
     * @throws \LogicException when the entry's content type has no layouts, because it has no URL of its own
     */
    public function render(EntryInterface $entry, RenderContext $context): string;
}
