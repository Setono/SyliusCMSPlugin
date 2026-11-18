<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;

/**
 * A snippet a slot shows through one of its placements, with its top-level blocks that show, ready to render, and the
 * render context they render with. The slot template renders them with cms_blocks(placed.blocks), with that context
 */
final readonly class PlacedSnippet
{
    /**
     * @param list<BlockView> $blocks
     * @param bool $previewed whether it's the draft of the snippet a snippet's preview or share link shows
     * @param RenderContext $context the context its blocks render with. In the builder's preview, only the previewed
     *                               snippet's blocks get the builder's preview mode, while the others show as shoppers
     *                               see them
     */
    public function __construct(
        public PlacementData $placement,
        public SnippetInterface $snippet,
        public array $blocks,
        public bool $previewed,
        public RenderContext $context,
    ) {
    }
}
