<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Field\ListField;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SourceMetadata;
use Setono\SyliusCMSPlugin\Registry\SourceRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Setono\SyliusCMSPlugin\Source\EntryContextAwareInterface;
use Setono\SyliusCMSPlugin\Source\StoredSource;
use Setono\SyliusCMSPlugin\Tree\StoredValue;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The list fields, in a block or in the tree's layout, filled by a source that needs context keys, such as Same taxon,
 * with the content types a source that fits only some as the entry fits, such as In this category.
 * A disabled source keeps resolving, so it counts. One the code no longer has, or of another kind than the list,
 * yields nothing, so it needs nothing. A list filled per locale counts once. See "The contract" in
 * docs/spec/sources.md
 *
 * @internal
 */
final readonly class SourceDependencies implements ContextDependencyFinderInterface
{
    public function __construct(
        private TreeValues $treeValues,
        private ContentTypeRegistryInterface $contentTypes,
        private SourceRegistryInterface $sources,
    ) {
    }

    public function findIn(EntryInterface $workingCopy): iterable
    {
        try {
            $contentType = $this->contentTypes->getByEntry($workingCopy)->code;
        } catch (UnknownContentTypeException) {
            return;
        }

        $found = [];
        foreach ($this->treeValues->inTree($workingCopy->getBlockTree(), $contentType) as $value) {
            $source = $this->needingSource($value);
            if (null === $source) {
                continue;
            }

            $block = $value->block?->key;
            $where = sprintf('%s %s %s', $block ?? '', $value->field->name, $source->code);
            if (isset($found[$where])) {
                continue;
            }
            $found[$where] = true;

            yield new ContextDependency($this->name($value, $source), $source->needs, block: $block, entryContentTypes: $this->entryContentTypes($source));
        }
    }

    /**
     * The source that fills the list field's value, when it needs context keys
     */
    private function needingSource(StoredValue $value): ?SourceMetadata
    {
        $field = $value->field->field;
        if (!$field instanceof ListField) {
            return null;
        }

        $stored = StoredSource::fromValue($value->value);
        if (null === $stored || !$this->sources->has($stored->code)) {
            return null;
        }

        $source = $this->sources->get($stored->code);

        return [] !== $source->needs && $source->lists($field->itemClass()) ? $source : null;
    }

    /**
     * The content types whose entries a source that fits only some fits, such as the category content types for In this
     * category, or null for one that fits any
     *
     * @return list<string>|null
     */
    private function entryContentTypes(SourceMetadata $source): ?array
    {
        if (!$source->source instanceof EntryContextAwareInterface) {
            return null;
        }

        $codes = array_map(static fn (ContentTypeMetadata $contentType): string => $contentType->code, $this->contentTypes->all());

        return array_values(array_filter($codes, $source->fitsEntryContentType(...)));
    }

    private function name(StoredValue $value, SourceMetadata $source): TranslatableMessage
    {
        if (null === $value->blockType) {
            return new TranslatableMessage('setono_sylius_cms.context.layout_source', [
                '%source%' => new TranslatableMessage($source->label),
                '%field%' => new TranslatableMessage($value->field->field->label),
            ], 'validators');
        }

        return new TranslatableMessage('setono_sylius_cms.context.source', [
            '%source%' => new TranslatableMessage($source->label),
            '%block%' => new TranslatableMessage($value->blockType->label),
        ], 'validators');
    }
}
