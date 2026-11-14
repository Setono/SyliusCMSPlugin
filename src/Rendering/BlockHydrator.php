<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
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
        private FallbackChainInterface $fallbackChain,
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
     * Hydrates a shared value, or a translatable value in the current locale. A translatable value falls back through
     * the fallback chain past values that are missing, empty or don't fit, which the handler hydrates as null,
     * such as an empty rich text document
     */
    private function hydrateField(FieldMetadata $field, mixed $value, string $localeCode, HydrationContext $context): mixed
    {
        if (!$field->translatable) {
            return null === $value ? null : $this->fieldHandlers->get($field->field)->hydrate($field->field, $value, $context);
        }

        if (!is_array($value)) {
            return null;
        }

        foreach ($this->fallbackChain->localesFor($localeCode) as $locale) {
            $localized = $value[$locale] ?? null;
            if (null === $localized || '' === $localized) {
                continue;
            }

            $hydrated = $this->fieldHandlers->get($field->field)->hydrate($field->field, $localized, $context);
            if (null !== $hydrated) {
                return $hydrated;
            }
        }

        return null;
    }
}
