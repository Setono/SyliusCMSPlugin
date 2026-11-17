<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles the list fields, Products and Taxons. A hand-picked list is an array of codes, hydrated as the items from the
 * references loaded before rendering, in the editor's order. Those references were loaded through the visible-products
 * and visible-taxons queries, so an item the shopper may not see, or that was deleted, is left out, and the list's count
 * is right. Every hand-picked item shows, whatever the field's limit. See "List fields" and "The contract" in
 * docs/spec/sources.md
 */
final class ListFieldHandler implements FitCheckingFieldHandlerInterface
{
    public function schema(Field $field): array
    {
        $field = self::listField($field);

        $schema = ['type' => 'array', 'items' => ['type' => 'string', 'minLength' => 1]];
        if (null !== $field->max) {
            $schema['maxItems'] = $field->max;
        }

        $schema['x-cms-options'] = ['max' => $field->max, 'sources' => $field->sources, 'limit' => $field->limit];

        return $schema;
    }

    /**
     * @return ItemList<object>|null
     */
    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?ItemList
    {
        $field = self::listField($field);

        return Codes::isCodeList($value) ? self::handPicked($field, $value, $context->references) : null;
    }

    /**
     * A list of codes fits, even one longer than the most, which still renders and which publish refuses
     */
    public function fits(Field $field, mixed $value): bool
    {
        return Codes::isCodeList($value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        $field = self::listField($field);

        return is_array($value) && array_is_list($value) ? Codes::references($field->itemType(), $value) : [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        $field = self::listField($field);

        if (null === $value) {
            return;
        }

        if (!Codes::isCodeList($value)) {
            $context->buildViolation('setono_sylius_cms.field.not_list')->addViolation();

            return;
        }

        if (null !== $field->max && count($value) > $field->max) {
            $context->buildViolation('setono_sylius_cms.field.too_many_items')
                ->setParameter('{{ limit }}', (string) $field->max)
                ->setPlural($field->max)
                ->addViolation()
            ;
        }
    }

    /**
     * The items the codes refer to that the shopper may see, in the codes' order
     *
     * @param list<non-empty-string> $codes
     *
     * @return ItemList<object>
     */
    private static function handPicked(ListField $field, array $codes, ReferenceMap $references): ItemList
    {
        $class = $field->itemClass();

        $items = [];
        foreach ($codes as $code) {
            $item = $references->get($field->itemType(), $code);
            if ($item instanceof $class) {
                $items[] = $item;
            }
        }

        return ItemList::of($items);
    }

    private static function listField(Field $field): ListField
    {
        if (!$field instanceof ListField) {
            throw new \InvalidArgumentException(sprintf('%s handles %s fields, got %s', self::class, ListField::class, $field::class));
        }

        return $field;
    }
}
