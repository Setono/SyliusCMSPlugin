<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;

/**
 * Reads an entry's fields for templates, as cms_field() and cms_value() give them: a content type's own field hydrated
 * by its field type, such as a Media field's media item from the UUID the property holds, with the records its value
 * refers to loaded for it, and a translatable one in the first locale of the fallback chain that has a value. A base
 * field, such as the title, is read as FallbackValues reads it. See "Content types" in docs/spec/extending.md
 */
final readonly class EntryFieldValues
{
    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private ContentTypeFields $contentTypeFields,
        private FieldHandlerLocator $fieldHandlers,
        private FallbackValues $fallbackValues,
        private ReferenceLoaderInterface $referenceLoader,
    ) {
    }

    /**
     * The value in the render context's locale, or null when it's missing, such as a broken reference
     *
     * @throws \InvalidArgumentException for a field the entry doesn't have, or the slug, which never falls back
     */
    public function value(EntryInterface $entry, string $name, RenderContext $context): mixed
    {
        $contentType = $this->contentTypes->getByEntry($entry);

        $shared = $this->contentTypeFields->shared($contentType)[$name] ?? null;
        if (null !== $shared) {
            return $this->hydrate($shared, $this->contentTypeFields->read($entry, $name), $context);
        }

        $translatable = $this->contentTypeFields->translatable($contentType)[$name] ?? null;
        if (null === $translatable) {
            return $this->fallbackValues->entryValue($entry, $name, $context->localeCode);
        }

        $translations = $entry->getTranslations();
        foreach ($this->fallbackValues->localesFor($context->localeCode) as $localeCode) {
            $translation = $translations->get($localeCode);
            if (null === $translation) {
                continue;
            }

            $hydrated = $this->hydrate($translatable, $this->contentTypeFields->read($translation, $name), $context);
            if (null !== $hydrated) {
                return $hydrated;
            }
        }

        return null;
    }

    /**
     * A missing value, or one that doesn't fit, is null
     */
    private function hydrate(FieldMetadata $field, mixed $value, RenderContext $context): mixed
    {
        if ($this->fallbackValues->isMissing($field->field, $value) || !$this->fallbackValues->fits($field->field, $value)) {
            return null;
        }

        $handler = $this->fieldHandlers->get($field->field);
        $references = $this->referenceLoader->load($handler->references($field->field, $value), $context);

        return $handler->hydrate($field->field, $value, new HydrationContext($context, $references));
    }
}
