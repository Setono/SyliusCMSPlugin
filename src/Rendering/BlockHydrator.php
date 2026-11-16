<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * Turns a stored block into its block type object, and a tree's layout into its layout object, whose properties hold
 * hydrated values in the current locale. See "Hydration" in docs/spec/rendering.md
 */
final readonly class BlockHydrator
{
    public function __construct(
        private FieldHandlerLocator $fieldHandlers,
        private FallbackValues $fallbackValues,
    ) {
    }

    public function hydrate(BlockTypeMetadata $blockType, StoredBlock $block, RenderContext $context): object
    {
        return $this->hydrateObject($blockType->class, $blockType->fields, $block->fields, $context);
    }

    /**
     * Turns stored field values into an object of the class, whose properties hold them hydrated in the current locale:
     * a block type's, or a layout's, whose values are stored the same way
     *
     * @param class-string $class
     * @param array<string, FieldMetadata> $fields
     * @param array<string, mixed> $values the stored values by field: a translatable field's by locale
     */
    public function hydrateObject(string $class, array $fields, array $values, RenderContext $context): object
    {
        $object = new $class();
        $hydrationContext = new HydrationContext($context);

        foreach ($fields as $name => $field) {
            $hydrated = $this->hydrateField($field, $values[$name] ?? null, $context->localeCode, $hydrationContext);
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
     * handler hydrates as null
     */
    private function hydrateField(FieldMetadata $field, mixed $value, string $localeCode, HydrationContext $context): mixed
    {
        $handler = $this->fieldHandlers->get($field->field);

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
