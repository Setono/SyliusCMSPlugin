<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A preset brought up to date with the code by PresetUpgrader: its blocks, and a starting point's layout, and what in
 * them was saved with a newer version than the code's, which is kept as it is
 *
 * @internal
 */
final readonly class UpgradedPreset
{
    /**
     * @param array<array-key, mixed> $tree the upgraded blocks and layout as a block tree, in the shape CanonicalJson::decode() gives
     * @param list<NewerVersion> $newer
     */
    public function __construct(
        private array $tree,
        public array $newer = [],
    ) {
    }

    /**
     * Whether a block or the layout was saved with a newer version than the code's, which inserting the preset and
     * creating from it refuse
     */
    public function hasNewer(): bool
    {
        return [] !== $this->newer;
    }

    /**
     * Why inserting the preset or creating from it is refused: one message per block or layout saved with a newer
     * version than the code's
     *
     * @return list<TranslatableMessage>
     */
    public function newerVersionMessages(): array
    {
        return array_map(static fn (NewerVersion $newer): TranslatableMessage => new TranslatableMessage('setono_sylius_cms.preset.newer_version', [
            '%type%' => $newer->code,
            '%version%' => $newer->version,
            '%code_version%' => $newer->codeVersion,
        ]), $this->newer);
    }

    /**
     * The upgraded blocks, with a starting point's layout, read back from the stored format
     *
     * @throws InvalidStoredTreeException when an upgrader left them out of it
     */
    public function tree(): StoredTree
    {
        return (new StoredTreeSerializer())->denormalize($this->tree);
    }
}
