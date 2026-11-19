<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetBlocks;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\PresetProblem;
use Setono\SyliusCMSPlugin\Problem\PresetProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A preset with blocks of a type the code no longer has, a warning, one per preset naming the types. Inserting it leaves
 * them out. What's inside such a block goes with it, so it isn't looked into. A code preset is fixed in code, and an
 * editor preset by saving over it. See "Presets with invalid blocks" in docs/spec/layouts-and-presets.md and "Problems"
 * in docs/spec/admin.md
 */
final readonly class PresetWithRemovedBlockTypes implements PresetProblemKindInterface
{
    public const CODE = 'preset_removed_block_type';

    public function __construct(private BlockTypeRegistryInterface $blockTypes)
    {
    }

    public function findIn(Preset $preset): iterable
    {
        $removed = [];
        foreach (PresetBlocks::walk($preset->blocks, $this->blockTypes) as [$block, $blockType]) {
            if (null === $blockType) {
                $removed[] = $block->type;
            }
        }

        $removed = array_values(array_unique($removed));
        if ([] === $removed) {
            return [];
        }

        return [new PresetProblem(
            self::CODE,
            Severity::Warning,
            $preset,
            new TranslatableMessage('setono_sylius_cms.problem.preset_removed_block_type', ['%types%' => implode(', ', array_map(static fn (string $type): string => sprintf('"%s"', $type), $removed))]),
            $preset->isFromCode() ? null : Fix::onPresetsScreen($preset),
        )];
    }
}
