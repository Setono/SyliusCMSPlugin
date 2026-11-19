<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

/**
 * Saves editor presets: what ★ saves in the builder, "Replace <name>", a rename or a new category, and an import.
 * Saving takes effect at once: presets aren't entries, so they have no drafts or revisions, and saving one never changes
 * the entries it was inserted into. See "Editor presets" in docs/spec/layouts-and-presets.md
 */
interface EditorPresetSaverInterface
{
    /**
     * Saves the preset as an editor preset and flushes. When an editor preset has its UUID, that one's content is
     * replaced by the preset's. Otherwise a new one is created, with the preset's UUID, as an import keeps it, or a new
     * one when it has none
     *
     * @param \DateTimeImmutable|null $changedAt its changedAt, such as the export's on an import, or null for now. The
     *                                           preset's own changedAt is left out
     *
     * @return Preset the editor preset as it's saved, with its UUID and changedAt
     */
    public function save(Preset $preset, ?\DateTimeImmutable $changedAt = null): Preset;
}
