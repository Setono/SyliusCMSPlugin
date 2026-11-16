<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A stored value that doesn't fit its field type, such as a string in a Toggle field or a choice the field no longer
 * offers, in a block or in the tree's layout: an error. It counts as missing, so the field shows nothing or falls back,
 * and publish refuses it. A translatable field's value is checked in each locale, and one that isn't a map from locale
 * to value doesn't fit at all. Missing values, such as a missing translation, are never a problem. The entry's own
 * fields are typed properties, which can't hold a value that doesn't fit. See "Values that don't fit" in
 * docs/spec/content-model.md and "Problems" in docs/spec/admin.md
 */
final readonly class MisfitValue implements ProblemKindInterface
{
    public const CODE = 'misfit_value';

    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private LayoutRegistryInterface $layouts,
        private ContentTypeRegistryInterface $contentTypes,
        private FallbackValues $fallbackValues,
    ) {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        $tree = $entry->getBlockTree();

        // A layout that was removed or disabled is another kind's problem, and the default layout ignores its values
        $layout = $tree->layout;
        $contentType = $this->contentTypes->getByEntry($entry)->code;
        if (null !== $layout && $this->layouts->has($contentType, $layout->code) && !$this->layouts->get($contentType, $layout->code)->disabled) {
            foreach ($this->misfits($this->layouts->get($contentType, $layout->code)->fields, $layout->fields) as [$field, $locale]) {
                yield new Problem(
                    self::CODE,
                    Severity::Error,
                    $entry,
                    new TranslatableMessage('setono_sylius_cms.problem.misfit_layout_value', ['%field%' => new TranslatableMessage($field->field->label)]),
                    Fix::inBuilder($entry, null, $locale),
                    null,
                    $field->name,
                    $locale,
                );
            }
        }

        yield from $this->inBlocks($entry, $tree->blocks);
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return \Generator<Problem>
     */
    private function inBlocks(EntryInterface $entry, array $blocks): \Generator
    {
        foreach ($blocks as $block) {
            // A block of a type the code no longer has is kept as it is, with what's inside it
            if (!$this->blockTypes->has($block->type)) {
                continue;
            }

            $blockType = $this->blockTypes->get($block->type);
            foreach ($this->misfits($blockType->fields, $block->fields) as [$field, $locale]) {
                yield new Problem(
                    self::CODE,
                    Severity::Error,
                    $entry,
                    new TranslatableMessage('setono_sylius_cms.problem.misfit_value', [
                        '%field%' => new TranslatableMessage($field->field->label),
                        '%block%' => new TranslatableMessage($blockType->label),
                    ]),
                    Fix::inBuilder($entry, $block->key, $locale),
                    $block->key,
                    $field->name,
                    $locale,
                );
            }

            yield from $this->inBlocks($entry, $block->children);
        }
    }

    /**
     * The fields whose stored values don't fit, each with the locale of the value, or null for a shared value or a
     * translatable value that isn't a locale map
     *
     * @param array<string, FieldMetadata> $fields
     * @param array<string, mixed> $values the stored values by field name
     *
     * @return \Generator<array{FieldMetadata, string|null}>
     */
    private function misfits(array $fields, array $values): \Generator
    {
        foreach ($fields as $name => $field) {
            $stored = $values[$name] ?? null;
            if (!$field->translatable) {
                if (!$this->fallbackValues->fits($field->field, $stored)) {
                    yield [$field, null];
                }

                continue;
            }

            if (null === $stored || '' === $stored || [] === $stored) {
                continue;
            }

            if (!is_array($stored) || array_is_list($stored)) {
                yield [$field, null];

                continue;
            }

            foreach ($stored as $locale => $value) {
                if (!$this->fallbackValues->fits($field->field, $value)) {
                    yield [$field, (string) $locale];
                }
            }
        }
    }
}
