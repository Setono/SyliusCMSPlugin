<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * A preset's blocks as inserting reads them. Inserting leaves out a block of a type the code no longer has, and one of
 * a disabled type, each with what's inside it, which the preset check, the Overview's problems and the insert notices
 * name. See "Presets with invalid blocks" in docs/spec/layouts-and-presets.md
 *
 * @internal
 */
final class PresetBlocks
{
    private function __construct()
    {
    }

    /**
     * Each block, depth first and parents before their children, with its block type, or with null when the code no
     * longer has its type: what's inside such a block goes with it, so it isn't walked into
     *
     * @param list<StoredBlock> $blocks
     * @param bool $intoDisabled whether to walk into a block of a disabled type, which inserting leaves out with what's
     *                           inside it
     *
     * @return \Generator<int, array{StoredBlock, BlockTypeMetadata|null}>
     */
    public static function walk(array $blocks, BlockTypeRegistryInterface $blockTypes, bool $intoDisabled = true): \Generator
    {
        foreach ($blocks as $block) {
            $blockType = self::typeOf($block, $blockTypes);
            yield [$block, $blockType];

            if (null !== $blockType && ($intoDisabled || !$blockType->disabled)) {
                yield from self::walk($block->children, $blockTypes, $intoDisabled);
            }
        }
    }

    /**
     * The block's type, or null when the code no longer has it
     */
    public static function typeOf(StoredBlock $block, BlockTypeRegistryInterface $blockTypes): ?BlockTypeMetadata
    {
        return $blockTypes->has($block->type) ? $blockTypes->get($block->type) : null;
    }
}
