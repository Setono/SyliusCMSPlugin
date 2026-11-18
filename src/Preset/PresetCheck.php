<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Registry\AllowedBlockType;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\NestingRulesInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Setono\SyliusCMSPlugin\Tree\StoredValue;
use Setono\SyliusCMSPlugin\Tree\StoredValueFit;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Finds what's wrong with a preset's blocks, as they'd be inserted once upgraded: blocks of removed or disabled types,
 * blocks saved with a newer version than the code's, children the nesting rules don't allow and too few or too many of
 * a type, values that don't fit their field types, and a starting point whose content type or layout the code doesn't
 * have. A block preset's top-level blocks may be of any type, as where they're allowed depends on where it's inserted,
 * but a starting point's are held to its content type's top level. Translation keys are read as inserting reads them,
 * so one fits a translatable text field, and not a shared one. setono:cms:preset:validate refuses a file for every kind
 * of issue but a disabled block type, and the Presets tab marks a preset for each. See "Code presets" and "Presets with
 * invalid blocks" in docs/spec/layouts-and-presets.md
 */
final readonly class PresetCheck
{
    /** The one locale a translation key is read in, which stands for any of an entry's */
    private const ANY_LOCALE = 'en_US';

    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private ContentTypeRegistryInterface $contentTypes,
        private LayoutRegistryInterface $layouts,
        private NestingRulesInterface $nestingRules,
        private StoredDataUpgraderInterface $upgrader,
        private TranslationKeyValues $translationKeyValues,
        private StoredValueFit $fit,
    ) {
    }

    /**
     * @return list<PresetIssue>
     */
    public function check(Preset $preset): array
    {
        $serializer = new StoredTreeSerializer();
        /** @var list<mixed> $stored */
        $stored = CanonicalJson::decode(CanonicalJson::encode(array_map($serializer->normalizeBlock(...), $preset->blocks)));
        $upgraded = $this->upgrader->upgradeBlocks($stored);
        if ([] !== $upgraded->newer) {
            return array_map(self::newerVersion(...), $upgraded->newer);
        }

        $blocks = array_map($serializer->denormalizeBlock(...), array_values($upgraded->data));
        $issues = $this->startingPointIssues($preset, $blocks);

        foreach ($blocks as $block) {
            $issues = [...$issues, ...$this->blockIssues($block)];
        }

        return $issues;
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return list<PresetIssue>
     */
    private function startingPointIssues(Preset $preset, array $blocks): array
    {
        $startingPoint = $preset->startingPoint;
        if (null === $startingPoint) {
            return [];
        }

        $contentTypeCode = $startingPoint->contentType;
        if (!$this->contentTypes->has($contentTypeCode)) {
            return [new PresetIssue(PresetIssueKind::StartingPoint, null, new TranslatableMessage('setono_sylius_cms.preset.issue.unknown_content_type', ['%content_type%' => $contentTypeCode]))];
        }

        $contentType = new TranslatableMessage($this->contentTypes->get($contentTypeCode)->label);
        $issues = [];
        $layout = $startingPoint->layout;
        if (null !== $layout && !$this->layouts->has($contentTypeCode, $layout->code)) {
            $issues[] = new PresetIssue(PresetIssueKind::StartingPoint, null, new TranslatableMessage('setono_sylius_cms.preset.issue.unknown_layout', ['%layout%' => $layout->code, '%content_type%' => $contentType]));
        } elseif (null !== $layout) {
            $upgraded = $this->upgrader->upgradeTree(['layout' => CanonicalJson::decode(CanonicalJson::encode((new StoredTreeSerializer())->normalizeLayout($layout))), 'blocks' => []], $contentTypeCode);
            if ([] !== $upgraded->newer) {
                return array_map(self::newerVersion(...), $upgraded->newer);
            }

            $upgradedLayout = (new StoredTreeSerializer())->denormalizeLayout($upgraded->data['layout'] ?? null);
            foreach ($this->values(TreeValues::inValues($this->layouts->get($contentTypeCode, $layout->code)->fields, $upgradedLayout->fields)) as $value) {
                if ($this->fit->doesNotFit($value)) {
                    $issues[] = new PresetIssue(PresetIssueKind::Misfit, null, new TranslatableMessage('setono_sylius_cms.problem.misfit_layout_value', ['%field%' => new TranslatableMessage($value->field->field->label)]));
                }
            }
        }

        return [...$issues, ...$this->listIssues($blocks, $this->nestingRules->topLevel($contentTypeCode), null, $contentType)];
    }

    /**
     * @return list<PresetIssue>
     */
    private function blockIssues(StoredBlock $block): array
    {
        if (!$this->blockTypes->has($block->type)) {
            return [new PresetIssue(PresetIssueKind::RemovedBlockType, $block->key, new TranslatableMessage('setono_sylius_cms.preset.issue.removed_block_type', ['%type%' => $block->type]))];
        }

        $blockType = $this->blockTypes->get($block->type);
        $label = new TranslatableMessage($blockType->label);
        $issues = [];
        if ($blockType->disabled) {
            $issues[] = new PresetIssue(PresetIssueKind::DisabledBlockType, $block->key, new TranslatableMessage('setono_sylius_cms.preset.issue.disabled_block_type', ['%block%' => $label]));
        }

        $values = [];
        foreach ($block->fields as $name => $value) {
            $field = $blockType->fields[$name] ?? null;
            $values[$name] = null === $field ? $value : $this->translationKeyValues->resolve($field, $value, [self::ANY_LOCALE], static fn (TranslationKey $key): string => $key->key);
        }

        foreach ($this->values(TreeValues::inValues($blockType->fields, $values, $block, $blockType)) as $value) {
            if ($this->fit->doesNotFit($value)) {
                $issues[] = new PresetIssue(PresetIssueKind::Misfit, $block->key, new TranslatableMessage('setono_sylius_cms.problem.misfit_value', [
                    '%field%' => new TranslatableMessage($value->field->field->label),
                    '%block%' => $label,
                ]));
            }
        }

        $issues = [...$issues, ...$this->listIssues($block->children, $this->nestingRules->children($block->type), $block->key, $label)];
        foreach ($block->children as $child) {
            $issues = [...$issues, ...$this->blockIssues($child)];
        }

        return $issues;
    }

    /**
     * What a list of blocks holds that its spot doesn't allow, and too few or too many of a type, which are issues of
     * the block that holds the list, or of the preset at a starting point's top level
     *
     * @param list<StoredBlock> $blocks
     * @param list<AllowedBlockType> $allowed
     * @param string|null $parent the key of the block that holds the list, or null at the top level
     * @param TranslatableMessage $parentLabel what holds the list: a block type, or a content type
     *
     * @return list<PresetIssue>
     */
    private function listIssues(array $blocks, array $allowed, ?string $parent, TranslatableMessage $parentLabel): array
    {
        $allowedCodes = array_map(static fn (AllowedBlockType $type): string => $type->code, $allowed);
        $issues = [];
        foreach ($blocks as $block) {
            if ($this->blockTypes->has($block->type) && !in_array($block->type, $allowedCodes, true)) {
                $issues[] = new PresetIssue(PresetIssueKind::Nesting, $block->key, new TranslatableMessage('setono_sylius_cms.preset.issue.not_allowed', [
                    '%block%' => new TranslatableMessage($this->blockTypes->get($block->type)->label),
                    '%parent%' => $parentLabel,
                ]));
            }
        }

        foreach ($allowed as $type) {
            $count = count(array_filter($blocks, static fn (StoredBlock $block): bool => $block->type === $type->code));
            $parameters = ['%block%' => new TranslatableMessage($this->blockTypes->get($type->code)->label), '%parent%' => $parentLabel];
            if (null !== $type->min && $count < $type->min) {
                $issues[] = new PresetIssue(PresetIssueKind::Nesting, $parent, new TranslatableMessage('setono_sylius_cms.preset.issue.too_few', ['%count%' => $type->min] + $parameters));
            }

            if (null !== $type->max && $count > $type->max) {
                $issues[] = new PresetIssue(PresetIssueKind::Nesting, $parent, new TranslatableMessage('setono_sylius_cms.preset.issue.too_many', ['%count%' => $type->max] + $parameters));
            }
        }

        return $issues;
    }

    private static function newerVersion(NewerVersion $newer): PresetIssue
    {
        return new PresetIssue(PresetIssueKind::NewerVersion, null, new TranslatableMessage('setono_sylius_cms.preset.newer_version', [
            '%type%' => $newer->code,
            '%version%' => $newer->version,
            '%code_version%' => $newer->codeVersion,
        ]));
    }

    /**
     * @param iterable<StoredValue> $values
     *
     * @return list<StoredValue>
     */
    private function values(iterable $values): array
    {
        $list = [];
        foreach ($values as $value) {
            $list[] = $value;
        }

        return $list;
    }
}
