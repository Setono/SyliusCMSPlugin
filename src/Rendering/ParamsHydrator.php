<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;

/**
 * Hydrates the parameters class of a condition kind or a source from the parameter values stored with a condition or a
 * list: each of its fields is hydrated by its field type's handler, a reference from the records loaded before
 * rendering. Parameter values are shared by all translations. A missing value, or one that doesn't fit, leaves the
 * property at its default. It also lists the references the values hold, which load with the tree's. See "The model"
 * in docs/spec/extending.md
 *
 * @internal
 */
final readonly class ParamsHydrator
{
    public function __construct(
        private FieldHandlerLocator $fieldHandlers,
        private FallbackValues $fallbackValues,
    ) {
    }

    /**
     * @param class-string|null $class the parameters class, or null for none, which gives a NoParams
     * @param array<string, FieldMetadata> $fields the parameters class's fields, by property name
     * @param array<array-key, mixed> $values the stored parameter values, by field name
     */
    public function hydrate(?string $class, array $fields, array $values, HydrationContext $context): object
    {
        return $this->doHydrate($class, $fields, $values, $context, false) ?? new NoParams();
    }

    /**
     * Hydrates as hydrate() does, or gives null when a parameter set to a reference no longer resolves: what it refers
     * to was deleted, or the shopper may not see it, such as a disabled taxon, or a choice from the store, such as an
     * association type, is one the store no longer has. A source then yields nothing, rather than what it yields
     * without the parameter. See "The contract" in docs/spec/sources.md
     *
     * @param class-string|null $class the parameters class, or null for none, which gives a NoParams
     * @param array<string, FieldMetadata> $fields the parameters class's fields, by property name
     * @param array<array-key, mixed> $values the stored parameter values, by field name
     */
    public function hydrateResolved(?string $class, array $fields, array $values, HydrationContext $context): ?object
    {
        return $this->doHydrate($class, $fields, $values, $context, true);
    }

    /**
     * @param class-string|null $class
     * @param array<string, FieldMetadata> $fields
     * @param array<array-key, mixed> $values
     * @param bool $resolvedOnly whether a reference that doesn't resolve gives null rather than the property's default
     */
    private function doHydrate(?string $class, array $fields, array $values, HydrationContext $context, bool $resolvedOnly): ?object
    {
        if (null === $class) {
            return new NoParams();
        }

        $params = new $class();

        foreach ($fields as $name => $field) {
            $value = $values[$name] ?? null;
            if ($this->fallbackValues->isMissing($field->field, $value)) {
                continue;
            }

            $handler = $this->fieldHandlers->get($field->field);
            $hydrated = $handler->hydrate($field->field, $value, $context);
            if (null === $hydrated) {
                if ($resolvedOnly && ([] !== [...$handler->references($field->field, $value)] || self::isChoiceFromTheStore($field->field))) {
                    return null;
                }

                continue;
            }

            try {
                (new \ReflectionProperty($params, $name))->setValue($params, $hydrated);
            } catch (\TypeError) {
                // A value that doesn't fit the property counts as missing, so the property keeps its default
            }
        }

        return $params;
    }

    /**
     * Whether the field is one Choice out of what a choices provider gives from the store, such as an association type,
     * which, like a reference, no longer resolves once the store no longer has what it was set to
     */
    private static function isChoiceFromTheStore(Field $field): bool
    {
        return $field instanceof Choice && null !== $field->choicesProvider && !$field->multiple;
    }

    /**
     * The references the stored parameter values hold, through each field type's handler
     *
     * @param array<string, FieldMetadata> $fields the parameters class's fields, by property name
     * @param array<array-key, mixed> $values the stored parameter values, by field name
     *
     * @return list<Reference>
     */
    public function references(array $fields, array $values): array
    {
        $references = [];
        foreach ($fields as $name => $field) {
            $value = $values[$name] ?? null;
            if (null === $value) {
                continue;
            }

            foreach ($this->fieldHandlers->get($field->field)->references($field->field, $value) as $reference) {
                $references[] = $reference;
            }
        }

        return $references;
    }
}
