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
     */
    public function __construct(
        public string $key,
        public string $type,
        public object $block,
        public ?string $styleClass,
        public array $children,
    ) {
    }
}
