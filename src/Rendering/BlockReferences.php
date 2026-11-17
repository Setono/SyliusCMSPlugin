<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Condition\ConditionMatcher;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The references rendering blocks needs loaded: those in their fields, then those in the parameters of their visibility
 * conditions, of the blocks and every block inside them. A block of a type the code no longer has isn't rendered, so
 * neither it nor what's inside it is read. See "Rendering a block tree" in docs/spec/rendering.md
 *
 * @internal
 */
final readonly class BlockReferences
{
    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private ConditionMatcher $conditions,
        private ReferenceFinder $referenceFinder,
    ) {
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return \Generator<Reference>
     */
    public function in(array $blocks): \Generator
    {
        yield from ReferenceFinder::references($this->referenceFinder->inBlocks($blocks));
        yield from $this->inConditions($blocks);
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return \Generator<Reference>
     */
    private function inConditions(array $blocks): \Generator
    {
        foreach ($blocks as $block) {
            if (!$this->blockTypes->has($block->type)) {
                continue;
            }

            yield from $this->conditions->references($block->conditions);
            yield from $this->inConditions($block->children);
        }
    }
}
