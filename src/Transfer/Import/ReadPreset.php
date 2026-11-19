<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;

/**
 * An editor preset of the export as the import read it, its blocks and a starting point's layout upgraded to the
 * code's versions
 */
final readonly class ReadPreset
{
    /**
     * @param Preset $preset with its UUID and the changedAt the export records
     * @param bool $picked whether the export picked it
     */
    public function __construct(
        public Preset $preset,
        public bool $picked,
    ) {
    }

    public function uuid(): string
    {
        return (string) $this->preset->uuid;
    }

    public function changedAt(): \DateTimeImmutable
    {
        return $this->preset->changedAt ?? throw new \LogicException('An editor preset in an export has its changedAt');
    }

    /**
     * What names it: its name
     */
    public function label(): string
    {
        $name = $this->preset->name;

        return $name instanceof TranslationKey ? $name->key : $name;
    }
}
