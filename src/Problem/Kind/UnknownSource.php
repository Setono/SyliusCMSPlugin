<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Field\ListField;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SourceRegistryInterface;
use Setono\SyliusCMSPlugin\Source\StoredSource;
use Setono\SyliusCMSPlugin\Tree\StoredValue;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
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
        private TreeValues $treeValues,
        private ContentTypeRegistryInterface $contentTypes,
        private SourceRegistryInterface $sources,
    ) {
    }

    /**
     * A layout that was removed or disabled is another kind's problem, and the default layout ignores its values, so
     * only the lists the tree renders are checked
     */
    public function findIn(EntryInterface $entry): iterable
    {
        foreach ($this->treeValues->inEntry($entry, $this->contentTypes->getByEntry($entry)) as $value) {
            $code = $this->unknownCode($value);
            if (null === $code) {
                continue;
            }

            $field = $value->field;
            if (null === $value->block || null === $value->blockType) {
                // In one of the content type's own fields, or in the layout
                yield new Problem(
                    self::CODE,
                    Severity::Warning,
                    $entry,
                    new TranslatableMessage($value->inEntry ? 'setono_sylius_cms.problem.unknown_entry_source' : 'setono_sylius_cms.problem.unknown_layout_source', ['%field%' => new TranslatableMessage($field->field->label), '%source%' => $code]),
                    Fix::inBuilder($entry, null, $value->locale),
                    null,
                    $field->name,
                    $value->locale,
                );

                continue;
            }

            yield new Problem(
                self::CODE,
                Severity::Warning,
                $entry,
                new TranslatableMessage('setono_sylius_cms.problem.unknown_source', [
                    '%field%' => new TranslatableMessage($field->field->label),
                    '%block%' => new TranslatableMessage($value->blockType->label),
                    '%source%' => $code,
                ]),
                Fix::inBuilder($entry, $value->block->key, $value->locale),
                $value->block->key,
                $field->name,
                $value->locale,
            );
        }
    }

    /**
     * The code of the source a list field's value holds, when the code no longer has it
     */
    private function unknownCode(StoredValue $value): ?string
    {
        if (!$value->field->field instanceof ListField) {
            return null;
        }

        $source = StoredSource::fromValue($value->value);

        return null === $source || $this->sources->has($source->code) ? null : $source->code;
    }
}
