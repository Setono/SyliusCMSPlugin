<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

/**
 * Deletes editor presets, at once. Deleting one never changes the entries it was inserted into, and its rows of the
 * media usage index go with it. See "Editor presets" in docs/spec/layouts-and-presets.md
 */
interface EditorPresetDeleterInterface
{
    /**
     * Deletes the editor preset with the UUID and flushes
     *
     * @return bool whether there was one to delete
     */
    public function delete(string $uuid): bool;
}
