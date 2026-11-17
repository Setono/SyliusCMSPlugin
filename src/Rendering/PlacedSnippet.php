<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;

/**
 * A snippet a slot shows through one of its placements, with its top-level blocks that show, ready to render. The slot
 * template renders them with cms_blocks(placed.blocks)
 */
final readonly class PlacedSnippet
{
    /**
     * @param list<BlockView> $blocks
     */
    public function __construct(
        public PlacementData $placement,
        public SnippetInterface $snippet,
        public array $blocks,
    ) {
    }
}
