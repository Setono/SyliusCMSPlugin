<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

/**
 * Asks every preset provider, the services tagged setono_sylius_cms.preset_provider, in the order of their priority:
 * code presets first. See "Presets" in docs/spec/layouts-and-presets.md
 */
final readonly class CompositePresetProvider implements PresetProviderInterface
{
    /**
     * @param iterable<PresetProviderInterface> $providers
     */
    public function __construct(private iterable $providers)
    {
    }

    public function all(): array
    {
        $presets = [];
        foreach ($this->providers as $provider) {
            $presets = [...$presets, ...$provider->all()];
        }

        return $presets;
    }

    public function find(string $id): ?Preset
    {
        foreach ($this->providers as $provider) {
            $preset = $provider->find($id);
            if (null !== $preset) {
                return $preset;
            }
        }

        return null;
    }
}
