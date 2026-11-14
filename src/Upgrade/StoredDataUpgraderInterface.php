<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * The upgrade service: it brings stored data up to date with the code whenever it's read, from the rows, drafts,
 * revisions, presets and export files. See "Schema changes" in docs/spec/content-model.md.
 *
 * - Blocks, the tree's layout and the entry run through their block type's, layout's and content type's upgraders,
 *   one version step each, from the version they were saved with to the code's. The version is set after each step
 * - A removed field's value, and its translation state, are dropped from blocks and layouts. A field the code added
 *   needs nothing: it takes its default
 * - Blocks of a type the code no longer has, and a layout it no longer has, are kept as they are
 * - What was saved with a newer version than the code's is kept as it is, and Upgraded::$newer lists it, so preset
 *   inserts and imports can refuse it
 *
 * Data is given and returned in the shape CanonicalJson::decode() gives. What isn't in the stored format, such as a
 * block without a version, is left as it is for StoredTreeSerializer and EntryDenormalizer to refuse
 */
interface StoredDataUpgraderInterface
{
    /**
     * @param array<array-key, mixed> $entry an entry in the entry format, with its tree
     */
    public function upgradeEntry(array $entry): Upgraded;

    /**
     * @param array<array-key, mixed> $tree a block tree in the stored format, of an entry of the content type with the code $contentType
     */
    public function upgradeTree(array $tree, string $contentType): Upgraded;

    /**
     * @param array<array-key, mixed> $blocks a list of blocks in the stored format, each with its children
     */
    public function upgradeBlocks(array $blocks): Upgraded;

    /**
     * The block tree in the entry's rows, upgraded. Read an entry's tree through here, not with EntryInterface::getBlockTree()
     *
     * @throws \Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException
     */
    public function readBlockTree(EntryInterface $entry): StoredTree;
}
