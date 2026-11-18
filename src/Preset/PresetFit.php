<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * Whether a preset is offered at a spot in an entry's tree, and if not, why. See "Where a preset is offered" in
 * docs/spec/layouts-and-presets.md
 */
final readonly class PresetFit
{
    /**
     * @param list<TranslatableMessage> $reasons why it isn't offered there, empty when it is
     */
    public function __construct(public array $reasons = [])
    {
    }

    public function fits(): bool
    {
        return [] === $this->reasons;
    }
}
