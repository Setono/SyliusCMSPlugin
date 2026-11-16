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
 * Turns a stored block into its block type object, whose properties hold hydrated values in the current locale.
 * See "Hydration" in docs/spec/rendering.md
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
        $class = $blockType->class;
        $object = new $class();
        $hydrationContext = new HydrationContext($context);

        foreach ($blockType->fields as $name => $field) {
            $hydrated = $this->hydrateField($field, $block->fields[$name] ?? null, $context->localeCode, $hydrationContext);
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
