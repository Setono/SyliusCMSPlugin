<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Binding\BoundValue;
use Setono\SyliusCMSPlugin\Block\FirstMatch;
use Setono\SyliusCMSPlugin\Block\Snippet;
use Setono\SyliusCMSPlugin\Condition\ConditionMatcher;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;

/**
 * The read pipeline's steps from stored blocks to block views. First one pass loads every reference the blocks hold,
 * in their fields and in their visibility conditions' parameters, with one query per type, and those of the trees of
 * the snippets they reference. Then visibility conditions run, before any template: a block whose conditions don't
 * match gets no view, and neither do its children, so a template gets only the children that show.
 *
 * A Snippet block's children are its snippet's blocks, built with the context of the tree it sits in. A snippet that
 * would render inside itself, directly or through other snippets, is skipped the second time: the Snippet block that
 * would show it again gets no children. See "Rendering a block tree" in docs/spec/rendering.md
 */
final readonly class BlockViewBuilder
{
    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private BlockHydrator $hydrator,
        private ConditionMatcher $conditions,
        private BlockReferences $blockReferences,
        private ReferenceLoaderInterface $referenceLoader,
        private StoredDataUpgraderInterface $upgrader,
    ) {
    }

    /**
     * @param list<StoredBlock> $blocks
     * @param ReferenceMap|null $references the references already loaded for the blocks, such as together with the
     *                                      layout's, or null to load the blocks' own
     * @param string|null $snippet the UUID of the snippet whose blocks they are, such as one shown in a slot, which then
     *                             doesn't show again inside them, or null for another entry's blocks
     *
     * @return list<BlockView>
     */
    public function build(array $blocks, RenderContext $context, ?ReferenceMap $references = null, ?string $snippet = null): array
    {
        $references ??= $this->referenceLoader->load($this->references($blocks), $context);

        return $this->views($blocks, $context, $references, false, null === $snippet ? [] : [$snippet]);
    }

    /**
     * The references rendering the blocks needs loaded: those in their fields, then those in the parameters of their
     * visibility conditions, of the blocks and every block inside them, leaving out a block of a type the code no
     * longer has and what's inside it, which isn't rendered. The references of the trees of the snippets they reference
     * aren't among them: ReferenceLoaderInterface adds those
     *
     * @param list<StoredBlock> $blocks
     *
     * @return \Generator<Reference>
     */
    public function references(array $blocks): \Generator
    {
        return $this->blockReferences->in($blocks);
    }

    /**
     * @param list<StoredBlock> $blocks
     * @param bool $firstOnly whether only the first block that shows gets a view, as in a First match block
     * @param list<string> $inside the UUIDs of the snippets whose blocks these are, outermost first
     *
     * @return list<BlockView>
     */
    private function views(array $blocks, RenderContext $context, ReferenceMap $references, bool $firstOnly, array $inside): array
    {
        $views = [];

        foreach ($blocks as $block) {
            // A block of a type the code no longer has isn't rendered, and nor is one its conditions hide
            if (!$this->blockTypes->has($block->type) || !$this->conditions->matchesAll($block->conditions, $context, $references)) {
                continue;
            }

            $blockType = $this->blockTypes->get($block->type);
            $hydrated = $this->hydrator->hydrate($blockType, $block, $context, $references);
            $views[] = new BlockView(
                $block->key,
                $block->type,
                $hydrated,
                $blockType->styleClass($block->style),
                $hydrated instanceof Snippet
                    ? $this->snippetViews($hydrated->snippet, $context, $references, $inside)
                    : $this->views($block->children, $context, $references, is_a($blockType->class, FirstMatch::class, true), $inside),
                array_keys(array_filter($blockType->fields, static fn (FieldMetadata $field): bool => BoundValue::isBound($field, $block->fields[$field->name] ?? null))),
            );

            if ($firstOnly) {
                break;
            }
        }

        return $views;
    }

    /**
     * The views of the snippet's blocks, as a Snippet block's children: none for a snippet the shopper may not see, or
     * one whose blocks these are already, which would render inside itself
     *
     * @param list<string> $inside
     *
     * @return list<BlockView>
     */
    private function snippetViews(?SnippetInterface $snippet, RenderContext $context, ReferenceMap $references, array $inside): array
    {
        if (null === $snippet || in_array($snippet->getUuid(), $inside, true)) {
            return [];
        }

        return $this->views($this->upgrader->readBlockTree($snippet)->blocks, $context, $references, false, [...$inside, $snippet->getUuid()]);
    }
}
