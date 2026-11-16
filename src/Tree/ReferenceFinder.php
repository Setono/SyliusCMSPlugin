<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;

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
        private BlockTypeRegistryInterface $blockTypes,
        private LayoutRegistryInterface $layouts,
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
        $layout = $tree->layout;
        if (null !== $layout && $this->layouts->has($contentType, $layout->code)) {
            $metadata = $this->layouts->get($contentType, $layout->code);
            if (!$metadata->disabled) {
                yield from $this->inValues($metadata->fields, $layout->fields);
            }
        }

        yield from $this->inBlocks($tree->blocks);
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
        foreach ($blocks as $block) {
            if (!$this->blockTypes->has($block->type)) {
                continue;
            }

            yield from $this->inValues($this->blockTypes->get($block->type)->fields, $block->fields, $block->key);
            yield from $this->inBlocks($block->children);
        }
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
        foreach ($fields as $name => $field) {
            $value = $values[$name] ?? null;
            if (null === $value) {
                continue;
            }

            $handler = $this->fieldHandlers->get($field->field);
            if (!$field->translatable) {
                foreach ($handler->references($field->field, $value) as $reference) {
                    yield new FoundReference($reference, $block, $name, null);
                }

                continue;
            }

            // A translatable value that isn't a locale map doesn't fit, and holds nothing
            if (!is_array($value) || array_is_list($value)) {
                continue;
            }

            foreach ($value as $locale => $localized) {
                foreach ($handler->references($field->field, $localized) as $reference) {
                    yield new FoundReference($reference, $block, $name, (string) $locale);
                }
            }
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
}
