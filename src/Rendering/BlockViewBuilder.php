<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Block\FirstMatch;
use Setono\SyliusCMSPlugin\Condition\ConditionMatcher;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The read pipeline's steps from stored blocks to block views. First one pass loads every reference the blocks hold,
 * in their fields and in their visibility conditions' parameters, with one query per type. Then visibility conditions
 * run, before any template: a block whose conditions don't match gets no view, and neither do its children, so a
 * template gets only the children that show. See "Rendering a block tree" in docs/spec/rendering.md
 */
final readonly class BlockViewBuilder
{
    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private BlockHydrator $hydrator,
        private ConditionMatcher $conditions,
        private ReferenceFinder $referenceFinder,
        private ReferenceLoaderInterface $referenceLoader,
    ) {
    }

    /**
     * @param list<StoredBlock> $blocks
     * @param ReferenceMap|null $references the references already loaded for the blocks, such as together with the
     *                                      layout's, or null to load the blocks' own
     *
     * @return list<BlockView>
     */
    public function build(array $blocks, RenderContext $context, ?ReferenceMap $references = null): array
    {
        $references ??= $this->referenceLoader->load($this->references($blocks), $context);

        return $this->views($blocks, $context, $references, false);
    }

    /**
     * The references rendering the blocks needs loaded: those in their fields, then those in the parameters of their
     * visibility conditions, of the blocks and every block inside them, leaving out a block of a type the code no
     * longer has and what's inside it, which isn't rendered
     *
     * @param list<StoredBlock> $blocks
     *
     * @return \Generator<Reference>
     */
    public function references(array $blocks): \Generator
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

    /**
     * @param list<StoredBlock> $blocks
     * @param bool $firstOnly whether only the first block that shows gets a view, as in a First match block
     *
     * @return list<BlockView>
     */
    private function views(array $blocks, RenderContext $context, ReferenceMap $references, bool $firstOnly): array
    {
        $views = [];

        foreach ($blocks as $block) {
            // A block of a type the code no longer has isn't rendered, and nor is one its conditions hide
            if (!$this->blockTypes->has($block->type) || !$this->conditions->matchesAll($block->conditions, $context, $references)) {
                continue;
            }

            $blockType = $this->blockTypes->get($block->type);
            $views[] = new BlockView(
                $block->key,
                $block->type,
                $this->hydrator->hydrate($blockType, $block, $context, $references),
                $blockType->styleClass($block->style),
                $this->views($block->children, $context, $references, is_a($blockType->class, FirstMatch::class, true)),
            );

            if ($firstOnly) {
                break;
            }
        }

        return $views;
    }
}
