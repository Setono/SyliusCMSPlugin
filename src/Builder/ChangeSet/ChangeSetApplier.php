<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\SanitizingFieldHandlerInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * Applies a change set to a block tree, all or nothing. The values themselves aren't validated,
 * because invalid content saves: publish validates. They're sanitised by field types that sanitise, such as rich text
 */
final readonly class ChangeSetApplier
{
    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private FieldHandlerLocator $fieldHandlers,
    ) {
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
                $change instanceof MoveBlock => $this->moveBlock($change, $tree),
                $change instanceof RemoveBlock => $this->removeBlock($change, $tree),
                $change instanceof SetValue => $this->setValue($change, $tree),
                default => throw new InvalidChangeSetException(sprintf('Unknown change %s', $change::class)),
            };
        }

        return $tree;
    }

    /**
     * The nesting rules aren't checked here: the builder refuses a drop or an insert that breaks them,
     * and data that breaks them anyway saves and is flagged
     */
    private function addBlock(AddBlock $change, StoredTree $tree): void
    {
        $this->assertKnownTypes($change->block);

        $taken = array_intersect($change->block->keys(), $tree->keys());
        if ([] !== $taken) {
            throw new InvalidChangeSetException(sprintf('The tree already has a block with the key %s', implode(', ', $taken)));
        }

        $this->sanitizeBlock($change->block);
        self::insert($tree, $change->block, $change->index, $change->parent);
    }

    private function moveBlock(MoveBlock $change, StoredTree $tree): void
    {
        $block = $tree->findBlock($change->key) ?? throw new InvalidChangeSetException(sprintf('The tree has no block with the key %s', $change->key));

        if (null !== $change->parent && in_array($change->parent, $block->keys(), true)) {
            throw new InvalidChangeSetException(sprintf('The block %s can\'t move inside itself', $change->key));
        }

        $tree->removeBlock($change->key);
        self::insert($tree, $block, $change->index, $change->parent);
    }

    private static function insert(StoredTree $tree, StoredBlock $block, int $index, ?string $parent): void
    {
        if (!$tree->insertBlock($block, $index, $parent)) {
            // Only a parent the tree doesn't have refuses an insert
            throw new InvalidChangeSetException(sprintf('The tree has no block with the key %s', $parent));
        }
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

        $block->setValue($change->field, $change->locale, null === $change->value ? null : $this->sanitize($field, $change->value));
    }

    /**
     * Sanitises the values of a block and the blocks inside it
     */
    private function sanitizeBlock(StoredBlock $block): void
    {
        $fields = $this->blockTypes->get($block->type)->fields;

        foreach ($block->fields as $name => $value) {
            $field = $fields[$name] ?? null;
            if (null === $field) {
                // A value the block type no longer has is ignored, and dropped on a later save
                continue;
            }

            if (!$field->translatable) {
                $block->fields[$name] = $this->sanitize($field, $value);
            } elseif (is_array($value)) {
                $block->fields[$name] = array_map(fn (mixed $localized): mixed => $this->sanitize($field, $localized), $value);
            }
        }

        foreach ($block->children as $child) {
            $this->sanitizeBlock($child);
        }
    }

    private function sanitize(FieldMetadata $field, mixed $value): mixed
    {
        $handler = $this->fieldHandlers->get($field->field);

        return $handler instanceof SanitizingFieldHandlerInterface ? $handler->sanitize($field->field, $value) : $value;
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
