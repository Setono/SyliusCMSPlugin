<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;

/**
 * Finds the references a stored block tree holds, through the field type handlers' references(): in each field of each
 * block and of the layout, in every locale of a translatable field. Reference loading before rendering reads them, and
 * so does the admin's broken references. A block of a type the code no longer has isn't rendered, so neither it nor
 * what's inside it is read, and nor is a layout the code no longer has or has disabled. See "References" in
 * docs/spec/content-model.md
 *
 * @internal
 */
final readonly class ReferenceFinder
{
    public function __construct(
        private TreeValues $treeValues,
        private FieldHandlerLocator $fieldHandlers,
    ) {
    }

    /**
     * The references in the tree's layout, then in its blocks
     *
     * @param string $contentType the code of the content type of the entry whose tree it is
     *
     * @return \Generator<FoundReference>
     */
    public function inTree(StoredTree $tree, string $contentType): \Generator
    {
        yield from $this->in($this->treeValues->inTree($tree, $contentType));
    }

    /**
     * The references in the blocks and in every block inside them, in tree order
     *
     * @param list<StoredBlock> $blocks
     *
     * @return \Generator<FoundReference>
     */
    public function inBlocks(array $blocks): \Generator
    {
        yield from $this->in($this->treeValues->inBlocks($blocks));
    }

    /**
     * The references in stored field values: a block's, or the layout's for a $block of null
     *
     * @param array<string, FieldMetadata> $fields
     * @param array<array-key, mixed> $values the stored values by field name: a translatable field's by locale
     *
     * @return \Generator<FoundReference>
     */
    public function inValues(array $fields, array $values, ?string $block = null): \Generator
    {
        foreach ($this->in(TreeValues::inValues($fields, $values)) as $found) {
            yield new FoundReference($found->reference, $block, $found->field, $found->locale);
        }
    }

    /**
     * Only the references of what was found
     *
     * @param iterable<FoundReference> $found
     *
     * @return \Generator<Reference>
     */
    public static function references(iterable $found): \Generator
    {
        foreach ($found as $reference) {
            yield $reference->reference;
        }
    }

    /**
     * @param iterable<StoredValue> $values
     *
     * @return \Generator<FoundReference>
     */
    private function in(iterable $values): \Generator
    {
        foreach ($values as $value) {
            // A translatable value that isn't a locale map doesn't fit, and holds nothing
            if ($value->isNotByLocale()) {
                continue;
            }

            foreach ($this->fieldHandlers->get($value->field->field)->references($value->field->field, $value->value) as $reference) {
                yield new FoundReference($reference, $value->block?->key, $value->field->name, $value->locale);
            }
        }
    }
}
