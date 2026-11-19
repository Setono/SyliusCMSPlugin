<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The blocks ★ saves as a preset: the selected block, or a ⌘-click selection. A selection under different parents
 * becomes the preset's top-level blocks in tree order, whatever order they were selected in, and a selected block inside
 * another selected block counts once, as part of its parent. See "Editor presets" in docs/spec/layouts-and-presets.md
 */
final class SelectedBlocks
{
    private function __construct()
    {
    }

    /**
     * Copies of the selected blocks, each with its children, so the tree is left as it is
     *
     * @param list<StoredBlock> $tree the blocks of the tree the selection is in
     * @param list<string> $keys the keys of the selected blocks
     *
     * @return non-empty-list<StoredBlock>
     *
     * @throws InvalidPresetRequestException for no selection, or a key the tree doesn't have
     */
    public static function in(array $tree, array $keys): array
    {
        $selected = array_fill_keys($keys, true);
        $blocks = self::topmost($tree, $selected);
        if ([] === $blocks) {
            throw new InvalidPresetRequestException(new TranslatableMessage('setono_sylius_cms.preset.request.select'));
        }

        if ([] !== $selected) {
            throw new InvalidPresetRequestException(new TranslatableMessage('setono_sylius_cms.preset.request.unknown_blocks', ['%keys%' => implode(', ', array_keys($selected))]));
        }

        return $blocks;
    }

    /**
     * The selected blocks in the list and below it, in tree order, without looking inside the selected ones. What it
     * finds, it takes out of $selected, including the blocks inside those it finds
     *
     * @param list<StoredBlock> $blocks
     * @param array<string, true> $selected the keys not yet found
     *
     * @return list<StoredBlock>
     */
    private static function topmost(array $blocks, array &$selected): array
    {
        $found = [];
        foreach ($blocks as $block) {
            if (!isset($selected[$block->key])) {
                $found = [...$found, ...self::topmost($block->children, $selected)];

                continue;
            }

            foreach ($block->keys() as $key) {
                unset($selected[$key]);
            }

            $found[] = clone $block;
        }

        return $found;
    }
}
