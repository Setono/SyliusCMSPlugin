<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding\Product;

use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The value of one of the product's attributes, the one its parameter names by code, as text: the first value along
 * the current locale's fallback chain. A select attribute's choices show their labels, joined with commas, a checkbox
 * shows Yes or No, and a date its ISO date. It's empty when the product has no value for it. The shipped bindable
 * property product.attribute is declared with it, so attributes need no declaration each. See "Shipped bindable
 * properties" in docs/spec/slots.md and ADR 0006
 *
 * @internal
 */
final readonly class AttributeProperty implements BindablePropertyInterface
{
    public function __construct(
        private FallbackChainInterface $fallbackChain,
        private TranslatorInterface $translator,
    ) {
    }

    public function resolve(object $subject, object $params, RenderContext $context): ?string
    {
        if (!$subject instanceof ProductInterface || !$params instanceof AttributeParams || null === $params->attribute) {
            return null;
        }

        foreach ($this->fallbackChain->localesFor($context->localeCode) as $locale) {
            $value = $subject->getAttributeByCodeAndLocale($params->attribute, $locale);
            $text = null === $value ? null : $this->text($value, $context->localeCode);
            if (null !== $text && '' !== $text) {
                return $text;
            }
        }

        return null;
    }

    private function text(AttributeValueInterface $value, string $localeCode): ?string
    {
        $stored = $value->getValue();

        return match (true) {
            is_string($stored) => $stored,
            is_int($stored), is_float($stored) => (string) $stored,
            is_bool($stored) => $this->translator->trans($stored ? 'sylius.ui.yes_label' : 'sylius.ui.no_label', [], null, $localeCode),
            $stored instanceof \DateTimeInterface => $stored->format('datetime' === $value->getType() ? 'Y-m-d H:i' : 'Y-m-d'),
            is_array($stored) => $this->choices($value, $stored, $localeCode),
            default => null,
        };
    }

    /**
     * The labels of a select attribute's chosen values, in the locale along its fallback chain, joined with commas
     *
     * @param array<array-key, mixed> $chosen
     */
    private function choices(AttributeValueInterface $value, array $chosen, string $localeCode): string
    {
        $configuration = $value->getAttribute()?->getConfiguration() ?? [];
        $choices = is_array($configuration['choices'] ?? null) ? $configuration['choices'] : [];

        $labels = [];
        foreach ($chosen as $key) {
            if (!is_string($key) && !is_int($key)) {
                continue;
            }

            $byLocale = $choices[$key] ?? null;
            $labels[] = is_array($byLocale) ? $this->label($byLocale, $localeCode) ?? (string) $key : (string) $key;
        }

        return implode(', ', $labels);
    }

    /**
     * @param array<array-key, mixed> $byLocale
     */
    private function label(array $byLocale, string $localeCode): ?string
    {
        foreach ($this->fallbackChain->localesFor($localeCode) as $locale) {
            $label = $byLocale[$locale] ?? null;
            if (is_string($label) && '' !== $label) {
                return $label;
            }
        }

        return null;
    }
}
