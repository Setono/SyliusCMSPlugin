<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;

/**
 * Hydrates the parameters class of a condition kind from the parameter values stored with a condition: each of its
 * fields is hydrated by its field type's handler. Parameter values are shared by all translations. A missing value, or
 * one that doesn't fit, leaves the property at its default. See "The model" in docs/spec/extending.md
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
        if (null === $class) {
            return new NoParams();
        }

        $params = new $class();

        foreach ($fields as $name => $field) {
            $value = $values[$name] ?? null;
            if ($this->fallbackValues->isMissing($field->field, $value)) {
                continue;
            }

            $hydrated = $this->fieldHandlers->get($field->field)->hydrate($field->field, $value, $context);
            if (null === $hydrated) {
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
}
