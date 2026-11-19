<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetException;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Writes an editor preset back upgraded, for setono:cms:upgrade: its blocks, and a starting point's layout, run through
 * the upgraders as a tree of the starting point's content type does. An upgrade isn't an editor's change, so the
 * preset keeps its changedAt. What was saved with a newer version than the code's is kept as it is, and a preset that
 * can't be read is left as it is
 *
 * @internal
 */
final readonly class PresetRewriter
{
    public function __construct(private StoredDataUpgraderInterface $upgrader)
    {
    }

    /**
     * Upgrades the preset's row in place, without flushing
     */
    public function rewrite(EditorPresetInterface $row): PresetRewrite
    {
        $content = new EditorPresetContent();

        try {
            $preset = $content->read($row);
        } catch (InvalidPresetException) {
            return new PresetRewrite(false, []);
        }

        $serializer = new StoredTreeSerializer();
        $startingPoint = $preset->startingPoint;
        /** @var array<array-key, mixed> $tree */
        $tree = CanonicalJson::decode(CanonicalJson::encode($serializer->normalize(new StoredTree($startingPoint?->layout, $preset->blocks))));
        if (null === $startingPoint) {
            $blocks = is_array($tree['blocks'] ?? null) ? $tree['blocks'] : [];
            $upgraded = $this->upgrader->upgradeBlocks($blocks);
            $tree['blocks'] = $upgraded->data;
        } else {
            $upgraded = $this->upgrader->upgradeTree($tree, $startingPoint->contentType);
            $tree = $upgraded->data;
        }

        try {
            $upgradedTree = $serializer->denormalize($tree);
        } catch (InvalidStoredTreeException) {
            return new PresetRewrite(false, $upgraded->newer);
        }

        $before = $row->getContent();
        $content->write($row, new Preset(
            $preset->name,
            $upgradedTree->blocks,
            $preset->category,
            $preset->description,
            null === $startingPoint ? null : new StartingPoint($startingPoint->contentType, $upgradedTree->layout),
            uuid: $preset->uuid,
            changedAt: $row->getChangedAt(),
        ));

        return new PresetRewrite($before !== $row->getContent(), $upgraded->newer);
    }
}
