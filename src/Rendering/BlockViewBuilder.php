<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The read pipeline's step from stored blocks to block views. See "Rendering a block tree" in docs/spec/rendering.md
 */
final readonly class BlockViewBuilder
{
    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private BlockHydrator $hydrator,
    ) {
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return list<BlockView>
     */
    public function build(array $blocks, RenderContext $context): array
    {
        $views = [];

        foreach ($blocks as $block) {
            // A block of a type the code no longer has isn't rendered
            if (!$this->blockTypes->has($block->type)) {
                continue;
            }

            $views[] = new BlockView(
                $block->key,
                $block->type,
                $this->hydrator->hydrate($this->blockTypes->get($block->type), $block, $context),
                null,
                $this->build($block->children, $context),
            );
        }

        return $views;
    }
}
