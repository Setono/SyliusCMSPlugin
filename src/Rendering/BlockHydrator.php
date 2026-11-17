<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Binding\BindingResolver;
use Setono\SyliusCMSPlugin\Binding\BoundValue;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Validation\FieldConstraints;

/**
 * Turns a stored block into its block type object, and a tree's layout into its layout object, whose properties hold
 * hydrated values in the current locale. See "Hydration" in docs/spec/rendering.md
 */
final readonly class BlockHydrator
{
    public function __construct(
        private FieldHandlerLocator $fieldHandlers,
        private FallbackValues $fallbackValues,
        private BindingResolver $bindings,
        private FieldConstraints $constraints,
    ) {
    }

    /**
     * @param ReferenceMap $references the records the tree's references resolve to, loaded before it renders
     */
    public function hydrate(BlockTypeMetadata $blockType, StoredBlock $block, RenderContext $context, ReferenceMap $references): object
    {
        return $this->hydrateObject($blockType->class, $blockType->fields, $block->fields, $context, $references);
    }

    /**
     * Turns stored field values into an object of the class, whose properties hold them hydrated in the current locale:
     * a block type's, or a layout's, whose values are stored the same way
     *
     * @param class-string $class
     * @param array<string, FieldMetadata> $fields
     * @param array<string, mixed> $values the stored values by field: a translatable field's by locale
     * @param ReferenceMap $references the records the tree's references resolve to, loaded before it renders
     */
    public function hydrateObject(string $class, array $fields, array $values, RenderContext $context, ReferenceMap $references): object
    {
        $object = new $class();
        $hydrationContext = new HydrationContext($context, $references);

        foreach ($fields as $name => $field) {
            $hydrated = $this->hydrateField($class, $field, $values[$name] ?? null, $context->localeCode, $hydrationContext);
            if (null === $hydrated) {
                continue;
            }

            try {
                (new \ReflectionProperty($object, $name))->setValue($object, $hydrated);
            } catch (\TypeError) {
                // A value that doesn't fit the property counts as missing, so the property keeps its default
            }
        }

        return $object;
    }

    /**
     * Hydrates a shared value, or a translatable value in the current locale. A missing value is null, and a
     * translatable value falls back through the fallback chain past values that are missing or don't fit, which the
     * handler hydrates as null. A binding is resolved first, as a value is read before its locale: when its value is
     * empty, or fails the field's own constraints, such as a number outside a field's range, its fallback is hydrated
     * as the field's value would be, and without one the field is empty
     *
     * @param class-string $class the class whose property declares the field, and carries its constraints
     */
    private function hydrateField(string $class, FieldMetadata $field, mixed $value, string $localeCode, HydrationContext $context): mixed
    {
        $handler = $this->fieldHandlers->get($field->field);

        if (BoundValue::isBound($field, $value)) {
            $binding = BoundValue::of($field, $value);
            if (null === $binding) {
                return null;
            }

            $bound = $this->bindings->resolve($binding, $field->type->bindable, $context);
            $hydrated = null === $bound || !$this->constraints->accept($class, $field, $bound) ? null : $handler->hydrate($field->field, $bound, $context);
            if (null !== $hydrated) {
                return $hydrated;
            }

            $value = $binding->fallback;
        }

        if (!$field->translatable) {
            return $this->fallbackValues->isMissing($field->field, $value) ? null : $handler->hydrate($field->field, $value, $context);
        }

        foreach ($this->fallbackValues->inFallbackOrder($field->field, $value, $localeCode) as $localized) {
            $hydrated = $handler->hydrate($field->field, $localized, $context);
            if (null !== $hydrated) {
                return $hydrated;
            }
        }

        return null;
    }
}
