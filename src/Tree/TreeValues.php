<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;

/**
 * Walks the stored values of a block tree the way it renders: the layout's, unless the code no longer has the layout or
 * has disabled it, then each block's, depth first, leaving out a block of a type the code no longer has and what's
 * inside it. A translatable field's value is given per locale. The reference finder and the problem kinds that read
 * values go through it
 *
 * @internal
 */
final readonly class TreeValues
{
    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private LayoutRegistryInterface $layouts,
    ) {
    }

    /**
     * @param string $contentType the code of the content type of the entry whose tree it is
     *
     * @return \Generator<StoredValue>
     */
    public function inTree(StoredTree $tree, string $contentType): \Generator
    {
        $layout = $tree->layout;
        if (null !== $layout && $this->layouts->has($contentType, $layout->code)) {
            $metadata = $this->layouts->get($contentType, $layout->code);
            if (!$metadata->disabled) {
                yield from self::inValues($metadata->fields, $layout->fields);
            }
        }

        yield from $this->inBlocks($tree->blocks);
    }

    /**
     * The values of the blocks and of every block inside them, in tree order
     *
     * @param list<StoredBlock> $blocks
     *
     * @return \Generator<StoredValue>
     */
    public function inBlocks(array $blocks): \Generator
    {
        foreach ($blocks as $block) {
            if (!$this->blockTypes->has($block->type)) {
                continue;
            }

            $blockType = $this->blockTypes->get($block->type);
            yield from self::inValues($blockType->fields, $block->fields, $block, $blockType);
            yield from $this->inBlocks($block->children);
        }
    }

    /**
     * The stored values of the fields that have one: a block's, or the layout's for a $block of null
     *
     * @param array<string, FieldMetadata> $fields
     * @param array<array-key, mixed> $values the stored values by field name: a translatable field's by locale
     *
     * @return \Generator<StoredValue>
     */
    public static function inValues(array $fields, array $values, ?StoredBlock $block = null, ?BlockTypeMetadata $blockType = null): \Generator
    {
        foreach ($fields as $name => $field) {
            $value = $values[$name] ?? null;
            if (null === $value) {
                continue;
            }

            if (!$field->translatable || !self::isLocaleMap($value)) {
                yield new StoredValue($field, $value, null, $block, $blockType);

                continue;
            }

            foreach ($value as $locale => $localized) {
                yield new StoredValue($field, $localized, (string) $locale, $block, $blockType);
            }
        }
    }

    /**
     * Every block, of any type, and every block inside it, in tree order
     *
     * @param list<StoredBlock> $blocks
     *
     * @return \Generator<StoredBlock>
     */
    public static function blocks(array $blocks): \Generator
    {
        foreach ($blocks as $block) {
            yield $block;
            yield from self::blocks($block->children);
        }
    }

    /**
     * Whether a translatable field's value is a map of locale codes to values. A special value, such as a source, isn't
     *
     * @phpstan-assert-if-true array<array-key, mixed> $value
     */
    private static function isLocaleMap(mixed $value): bool
    {
        if (!is_array($value) || array_is_list($value)) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (str_starts_with((string) $key, '$')) {
                return false;
            }
        }

        return true;
    }
}
