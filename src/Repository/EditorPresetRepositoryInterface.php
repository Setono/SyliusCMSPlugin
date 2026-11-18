<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;

/**
 * The presets editors saved. Read them as presets through Preset\PresetProviderInterface, and write them through
 * Preset\EditorPresetSaverInterface and Preset\EditorPresetDeleterInterface. See "Editor presets" in
 * docs/spec/layouts-and-presets.md
 */
interface EditorPresetRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?EditorPresetInterface;

    /**
     * Every editor preset, the one saved first first
     *
     * @return list<EditorPresetInterface>
     */
    public function findAllInCreationOrder(): array;
}
