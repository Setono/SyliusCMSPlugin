<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;

/**
 * Brings a preset up to date with the code as it's read, as an entry's tree is: a block preset's blocks run through the
 * upgraders, and a starting point's layout and blocks as a tree of its content type. What was saved with a newer
 * version than the code's is kept as it is and listed, and each caller acts on it: inserting and creating from a
 * starting point refuse the preset, validation reports it, an import refuses it, and setono:cms:upgrade writes the rest
 * back. See "Schema changes" in docs/spec/content-model.md
 *
 * @internal
 */
final readonly class PresetUpgrader
{
    public function __construct(private StoredDataUpgraderInterface $upgrader)
    {
    }

    public function upgrade(Preset $preset): UpgradedPreset
    {
        $startingPoint = $preset->startingPoint;
        /** @var array<array-key, mixed> $tree */
        $tree = CanonicalJson::decode(CanonicalJson::encode((new StoredTreeSerializer())->normalize(new StoredTree($startingPoint?->layout, $preset->blocks))));

        if (null !== $startingPoint) {
            $upgraded = $this->upgrader->upgradeTree($tree, $startingPoint->contentType);

            return new UpgradedPreset($upgraded->data, $upgraded->newer);
        }

        $upgraded = $this->upgrader->upgradeBlocks(is_array($tree['blocks'] ?? null) ? $tree['blocks'] : []);
        $tree['blocks'] = $upgraded->data;

        return new UpgradedPreset($tree, $upgraded->newer);
    }
}
