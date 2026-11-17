<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Field\ListField;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SourceRegistryInterface;
use Setono\SyliusCMSPlugin\Source\StoredSource;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A list field, in a block or in the tree's layout, filled by a source the code no longer has, a warning. It yields
 * nothing, and publish refuses it. It's fixed in the builder, by filling the list another way or bringing the source
 * back. A disabled source isn't a problem: the lists it fills keep resolving through it. See "The contract" in
 * docs/spec/sources.md and "Problems" in docs/spec/admin.md
 */
final readonly class UnknownSource implements ProblemKindInterface
{
    public const CODE = 'unknown_source';

    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private LayoutRegistryInterface $layouts,
        private ContentTypeRegistryInterface $contentTypes,
        private SourceRegistryInterface $sources,
    ) {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        $tree = $entry->getBlockTree();

        // A layout that was removed or disabled is another kind's problem, and the default layout ignores its values
        $layout = $tree->layout;
        $contentType = $this->contentTypes->getByEntry($entry)->code;
        if (null !== $layout && $this->layouts->has($contentType, $layout->code) && !$this->layouts->get($contentType, $layout->code)->disabled) {
            foreach ($this->unknownSources($this->layouts->get($contentType, $layout->code)->fields, $layout->fields) as [$field, $locale, $code]) {
                yield new Problem(
                    self::CODE,
                    Severity::Warning,
                    $entry,
                    new TranslatableMessage('setono_sylius_cms.problem.unknown_layout_source', ['%field%' => new TranslatableMessage($field->field->label), '%source%' => $code]),
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
            foreach ($this->unknownSources($blockType->fields, $block->fields) as [$field, $locale, $code]) {
                yield new Problem(
                    self::CODE,
                    Severity::Warning,
                    $entry,
                    new TranslatableMessage('setono_sylius_cms.problem.unknown_source', [
                        '%field%' => new TranslatableMessage($field->field->label),
                        '%block%' => new TranslatableMessage($blockType->label),
                        '%source%' => $code,
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
     * The list fields filled by a source the code doesn't have, each with the locale of the value, or null for a shared
     * value, and the source's code
     *
     * @param array<string, FieldMetadata> $fields
     * @param array<string, mixed> $values the stored values by field name
     *
     * @return \Generator<array{FieldMetadata, string|null, string}>
     */
    private function unknownSources(array $fields, array $values): \Generator
    {
        foreach ($fields as $name => $field) {
            if (!$field->field instanceof ListField) {
                continue;
            }

            $stored = $values[$name] ?? null;
            if ($field->translatable && is_array($stored) && !array_is_list($stored) && !StoredSource::isSource($stored)) {
                foreach ($stored as $locale => $value) {
                    $code = $this->unknownCode($value);
                    if (null !== $code) {
                        yield [$field, (string) $locale, $code];
                    }
                }

                continue;
            }

            $code = $this->unknownCode($stored);
            if (null !== $code) {
                yield [$field, null, $code];
            }
        }
    }

    /**
     * The code of the source the value holds, when the code no longer has it
     */
    private function unknownCode(mixed $value): ?string
    {
        $source = StoredSource::fromValue($value);

        return null === $source || $this->sources->has($source->code) ? null : $source->code;
    }
}
