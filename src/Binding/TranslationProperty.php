<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding;

use Doctrine\Common\Collections\Collection;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * A translated field of the subject, or of a record the subject holds, such as a product's name or its main taxon's
 * name: the first value that isn't empty in the translations along the current locale's fallback chain. A subject
 * without translations, or a path that reaches none, is empty. It reads the translations as they are, so one that's
 * missing is never created. The shipped bindable properties product.name, product.short_description,
 * product.description, product.main_taxon_name, taxon.name, taxon.description and entry.title are declared with it.
 * See "Shipped bindable properties" in docs/spec/slots.md
 *
 * @internal
 */
final readonly class TranslationProperty implements BindablePropertyInterface
{
    private PropertyAccessorInterface $propertyAccessor;

    /**
     * @param string $field the translation's property, such as "name"
     * @param string|null $of a property path from the subject to the translatable record, such as "mainTaxon", or null
     *                        for the subject itself
     */
    public function __construct(
        private string $field,
        private FallbackChainInterface $fallbackChain,
        private ?string $of = null,
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->disableExceptionOnInvalidPropertyPath()->getPropertyAccessor();
    }

    public function resolve(object $subject, object $params, RenderContext $context): ?string
    {
        $translatable = null === $this->of ? $subject : $this->propertyAccessor->getValue($subject, $this->of);
        if (!is_object($translatable) || !method_exists($translatable, 'getTranslations')) {
            return null;
        }

        $translations = $translatable->getTranslations();
        if (!$translations instanceof Collection) {
            return null;
        }

        foreach ($this->fallbackChain->localesFor($context->localeCode) as $locale) {
            $translation = $translations->get($locale);
            if (!is_object($translation)) {
                continue;
            }

            $value = $this->propertyAccessor->getValue($translation, $this->field);
            if (is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return null;
    }
}
