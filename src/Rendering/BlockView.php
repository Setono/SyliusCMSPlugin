<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

/**
 * A block ready to render. Templates render one with cms_block(view) and a list with cms_blocks(views)
 */
final readonly class BlockView
{
    /**
     * @param string $type the block type's code
     * @param object $block the hydrated block type object
     * @param string|null $styleClass the chosen block style's class
     * @param list<BlockView> $children only the children that show
     * @param list<string> $bound the names of the fields that show a binding, which can't be typed in place
     * @param string|null $entry the UUID of the entry whose tree holds it, such as the snippet a Snippet block shows, or
     *                           null for blocks that are no entry's, such as those the builder sends to be previewed
     */
    public function __construct(
        public string $key,
        public string $type,
        public object $block,
        public ?string $styleClass,
        public array $children,
        public array $bound = [],
        public ?string $entry = null,
    ) {
    }
}
