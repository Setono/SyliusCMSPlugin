<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * Applies a change set to a block tree, all or nothing. The values themselves aren't validated,
 * because invalid content saves: publish validates
 */
final readonly class ChangeSetApplier
{
    public function __construct(private BlockTypeRegistryInterface $blockTypes)
    {
    }

    /**
     * @return StoredTree a new tree with the changes applied; $tree is left as it is
     *
     * @throws InvalidChangeSetException
     */
    public function apply(ChangeSet $changeSet, StoredTree $tree): StoredTree
    {
        $tree = clone $tree;

        foreach ($changeSet->changes as $change) {
            match (true) {
                $change instanceof AddBlock => $this->addBlock($change, $tree),
                $change instanceof RemoveBlock => $this->removeBlock($change, $tree),
                $change instanceof SetValue => $this->setValue($change, $tree),
                default => throw new InvalidChangeSetException(sprintf('Unknown change %s', $change::class)),
            };
        }

        return $tree;
    }

    private function addBlock(AddBlock $change, StoredTree $tree): void
    {
        // Nesting, and the rules on what a spot may hold, come with #218
        if (null !== $change->parent) {
            throw new InvalidChangeSetException('Blocks can only be added at the top level');
        }

        $this->assertKnownTypes($change->block);

        $taken = array_intersect($change->block->keys(), $tree->keys());
        if ([] !== $taken) {
            throw new InvalidChangeSetException(sprintf('The tree already has a block with the key %s', implode(', ', $taken)));
        }

        $tree->insertBlock($change->block, $change->index);
    }

    private function removeBlock(RemoveBlock $change, StoredTree $tree): void
    {
        if (!$tree->removeBlock($change->key)) {
            throw new InvalidChangeSetException(sprintf('The tree has no block with the key %s', $change->key));
        }
    }

    private function setValue(SetValue $change, StoredTree $tree): void
    {
        $block = $tree->findBlock($change->key) ?? throw new InvalidChangeSetException(sprintf('The tree has no block with the key %s', $change->key));
        $this->assertKnownTypes($block);

        $field = $this->blockTypes->get($block->type)->fields[$change->field] ?? throw new InvalidChangeSetException(sprintf('The block type "%s" has no field "%s"', $block->type, $change->field));

        if ($field->translatable && null === $change->locale) {
            throw new InvalidChangeSetException(sprintf('The field "%s" is translatable, so a value needs a locale', $change->field));
        }

        if (!$field->translatable && null !== $change->locale) {
            throw new InvalidChangeSetException(sprintf('The field "%s" is shared by all translations, so a value takes no locale', $change->field));
        }

        $block->setValue($change->field, $change->locale, $change->value);
    }

    private function assertKnownTypes(StoredBlock $block): void
    {
        if (!$this->blockTypes->has($block->type)) {
            throw new InvalidChangeSetException(sprintf('There is no block type with the code "%s"', $block->type));
        }

        foreach ($block->children as $child) {
            $this->assertKnownTypes($child);
        }
    }
}
