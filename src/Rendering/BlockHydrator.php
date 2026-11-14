<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
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
            $value = $block->fields[$name] ?? null;
            if ($field->translatable) {
                $value = $this->inLocale($value, $context->localeCode);
            }

            if (null === $value) {
                continue;
            }

            $hydrated = $this->fieldHandlers->get($field->field)->hydrate($field->field, $value, $hydrationContext);
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
     * Picks a translatable value in the current locale, falling back through the fallback chain past missing and empty values
     */
    private function inLocale(mixed $values, string $localeCode): mixed
    {
        if (!is_array($values)) {
            return null;
        }

        foreach ($this->fallbackChain->localesFor($localeCode) as $locale) {
            $value = $values[$locale] ?? null;
            if (null !== $value && '' !== $value) {
                return $value;
            }
        }

        return null;
    }
}
