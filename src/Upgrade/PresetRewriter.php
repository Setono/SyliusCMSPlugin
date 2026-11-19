<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetException;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetUpgrader;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;

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
    public function __construct(private PresetUpgrader $presetUpgrader)
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

        $upgraded = $this->presetUpgrader->upgrade($preset);

        try {
            $upgradedTree = $upgraded->tree();
        } catch (InvalidStoredTreeException) {
            return new PresetRewrite(false, $upgraded->newer);
        }

        $startingPoint = $preset->startingPoint;
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
