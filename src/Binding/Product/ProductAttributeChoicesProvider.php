<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding\Product;

use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Sylius\Component\Attribute\Model\AttributeTranslationInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The store's product attributes, by code, with their names in the locale, for the Product attribute binding's
 * attribute parameter, and for the product_attribute search. They're read once per request, ordered by position
 *
 * @internal
 */
final class ProductAttributeChoicesProvider implements ChoicesProviderInterface, ResetInterface
{
    /** @var array<string, array<array-key, string>> locale code => choices */
    private array $choices = [];

    /**
     * @param RepositoryInterface<ProductAttributeInterface> $attributeRepository
     */
    public function __construct(private readonly RepositoryInterface $attributeRepository)
    {
    }

    public function getChoices(string $localeCode): array
    {
        if (isset($this->choices[$localeCode])) {
            return $this->choices[$localeCode];
        }

        $choices = [];
        foreach ($this->attributeRepository->findBy([], ['position' => 'ASC']) as $attribute) {
            $code = $attribute->getCode();
            if (null === $code) {
                continue;
            }

            // Read as it is, so a missing translation is never created
            $translation = $attribute->getTranslations()->get($localeCode);
            $name = $translation instanceof AttributeTranslationInterface ? $translation->getName() : null;
            $choices[$code] = null === $name || '' === $name ? ($attribute->getName() ?? $code) : $name;
        }

        return $this->choices[$localeCode] = $choices;
    }

    public function reset(): void
    {
        $this->choices = [];
    }
}
