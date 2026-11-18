<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

/**
 * The presets editors are offered: code presets, read from the files in setono_sylius_cms.presets.paths, and editor
 * presets, saved in the builder. Each kind is a provider tagged setono_sylius_cms.preset_provider, and
 * CompositePresetProvider, which this interface is an alias of, asks them all. See "Presets" in
 * docs/spec/layouts-and-presets.md
 */
interface PresetProviderInterface
{
    /**
     * Every preset editors are offered, those switched off left out
     *
     * @return list<Preset>
     */
    public function all(): array;

    /**
     * The preset with the id, a code preset's code or an editor preset's UUID (Preset::id()), or null when there's none
     * that editors are offered
     */
    public function find(string $id): ?Preset;
}
