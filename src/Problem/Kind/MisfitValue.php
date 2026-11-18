<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Binding\BoundValue;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredValue;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
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
        private TreeValues $treeValues,
        private ContentTypeRegistryInterface $contentTypes,
        private FallbackValues $fallbackValues,
    ) {
    }

    /**
     * A layout that was removed or disabled is another kind's problem, and the default layout ignores its values, so
     * only the values the tree renders are checked
     */
    public function findIn(EntryInterface $entry): iterable
    {
        foreach ($this->treeValues->inEntry($entry, $this->contentTypes->getByEntry($entry)) as $value) {
            if (!$this->doesNotFit($value)) {
                continue;
            }

            $field = $value->field;
            if (null === $value->block || null === $value->blockType) {
                // In one of the content type's own fields, or in the layout
                yield new Problem(
                    self::CODE,
                    Severity::Error,
                    $entry,
                    new TranslatableMessage($value->inEntry ? 'setono_sylius_cms.problem.misfit_entry_value' : 'setono_sylius_cms.problem.misfit_layout_value', ['%field%' => new TranslatableMessage($field->field->label)]),
                    Fix::inBuilder($entry, null, $value->locale),
                    null,
                    $field->name,
                    $value->locale,
                );

                continue;
            }

            yield new Problem(
                self::CODE,
                Severity::Error,
                $entry,
                new TranslatableMessage('setono_sylius_cms.problem.misfit_value', [
                    '%field%' => new TranslatableMessage($field->field->label),
                    '%block%' => new TranslatableMessage($value->blockType->label),
                ]),
                Fix::inBuilder($entry, $value->block->key, $value->locale),
                $value->block->key,
                $field->name,
                $value->locale,
            );
        }
    }

    /**
     * A translatable field's value that isn't a locale map doesn't fit, unless it's empty, and nor does a value its
     * field type doesn't take. A binding fits a field that can be bound, in its shape
     */
    private function doesNotFit(StoredValue $value): bool
    {
        if (BoundValue::isBound($value->field, $value->value)) {
            return null === BoundValue::of($value->field, $value->value);
        }

        if ($value->isNotByLocale()) {
            return '' !== $value->value && [] !== $value->value;
        }

        return !$this->fallbackValues->fits($value->field->field, $value->value);
    }
}
