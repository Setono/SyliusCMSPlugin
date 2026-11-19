<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Registry\SourceMetadata;
use Setono\SyliusCMSPlugin\Registry\SourceRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Source\SourceResolver;
use Setono\SyliusCMSPlugin\Source\StoredSource;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles the list fields, Products, Taxons and Entries, hand-picked or filled by a source.
 *
 * A hand-picked list is an array of codes, hydrated as the items from the references loaded before rendering, in the
 * editor's order. Those references were loaded through the visible-products and visible-taxons queries, so an item the
 * shopper may not see, or that was deleted, is left out, and the list's count is right. Every hand-picked item shows,
 * whatever the field's limit.
 *
 * A list a source fills is {"$source": code, "$params": {…}, "$limit": n}, hydrated as a list that resolves the source
 * when a template first reads it, and shows its first $limit items without counting them. Its parameters' references
 * load with the tree's before rendering. Publish refuses a source the field's sources option doesn't allow, but one it
 * holds keeps resolving, as a disabled source does, so narrowing the option in the code doesn't empty what's live. See "List fields" and "The contract" in docs/spec/sources.md
 */
final readonly class ListFieldHandler implements FitCheckingFieldHandlerInterface
{
    public function __construct(
        private SourceRegistryInterface $sources,
        private SourceResolver $sourceResolver,
    ) {
    }

    /**
     * A list of codes, or a source the field offers. x-cms-options has the codes of the sources it offers, in the order
     * the registry has them, so the builder's "Filled by" select offers them after Hand-picked
     */
    public function schema(Field $field): array
    {
        $field = self::listField($field);

        $handPicked = ['type' => 'array', 'items' => ['type' => 'string', 'minLength' => 1]];
        if (null !== $field->max) {
            $handPicked['maxItems'] = $field->max;
        }

        $offered = array_values(array_map(
            static fn (SourceMetadata $source): string => $source->code,
            array_filter($this->sources->all(), $field->offers(...)),
        ));

        $schema = [] === $offered ? $handPicked : ['anyOf' => [$handPicked, [
            'type' => 'object',
            'properties' => [
                StoredSource::SOURCE => ['type' => 'string', 'minLength' => 1],
                StoredSource::PARAMS => ['type' => 'object'],
                StoredSource::LIMIT => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => [StoredSource::SOURCE, StoredSource::PARAMS, StoredSource::LIMIT],
            'additionalProperties' => false,
        ]]];
        $schema['x-cms-options'] = ['max' => $field->max, 'sources' => $offered, 'limit' => $field->limit];

        return $schema;
    }

    /**
     * @return ItemList<mixed>|null
     */
    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?ItemList
    {
        $field = self::listField($field);

        if (Codes::isCodeList($value)) {
            return self::handPicked($field, $value, $context->references);
        }

        $stored = StoredSource::fromValue($value);
        if (null === $stored) {
            return null;
        }

        $resolver = $this->sourceResolver;
        $of = $field->itemClass();

        return ItemList::lazy(static fn () => $resolver->resolve($stored->code, $stored->params, $of, $context), $stored->limit);
    }

    /**
     * A list of codes fits, even one longer than the most, which still renders and which publish refuses, and so does
     * a source in its shape, even one the code no longer has, which is another problem
     */
    public function fits(Field $field, mixed $value): bool
    {
        return Codes::isCodeList($value) || null !== StoredSource::fromValue($value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        $field = self::listField($field);

        if (is_array($value) && array_is_list($value)) {
            return Codes::references($field->itemType(), $value);
        }

        $stored = StoredSource::fromValue($value);

        return null === $stored ? [] : $this->sourceResolver->references($stored->code, $stored->params);
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        $field = self::listField($field);

        if (null === $value) {
            return;
        }

        if (StoredSource::isSource($value)) {
            $stored = StoredSource::fromValue($value);
            if (null === $stored) {
                $context->buildViolation('setono_sylius_cms.field.not_source')->addViolation();

                return;
            }

            $this->sourceResolver->validate($stored->code, $stored->params, $field, $context);

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
     * @return ItemList<mixed>
     */
    private static function handPicked(ListField $field, array $codes, ReferenceMap $references): ItemList
    {
        /** @var list<mixed> $items */
        $items = [];
        foreach ($codes as $code) {
            $item = $references->get($field->itemType(), $code);
            if ($field->accepts($item)) {
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
