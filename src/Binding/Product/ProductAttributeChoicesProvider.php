<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding\Product;

use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Setono\SyliusCMSPlugin\Localization\TranslatedName;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The store's product attributes, by code, with their names in the locale asked for, or their codes where they have no
 * name in it, ordered by position, for the Product attribute binding's attribute parameter, and for the
 * product_attribute search. They're read once per request
 *
 * @internal
 */
final class ProductAttributeChoicesProvider implements ChoicesProviderInterface, ResetInterface
{
    /** @var array<ProductAttributeInterface>|null */
    private ?array $attributes = null;

    /**
     * @param RepositoryInterface<ProductAttributeInterface> $attributeRepository
     */
    public function __construct(private readonly RepositoryInterface $attributeRepository)
    {
    }

    public function getChoices(string $localeCode): array
    {
        $this->attributes ??= $this->attributeRepository->findBy([], ['position' => 'ASC']);

        $choices = [];
        foreach ($this->attributes as $attribute) {
            $code = $attribute->getCode();
            if (null !== $code) {
                $choices[$code] = TranslatedName::of($attribute, $localeCode) ?? $code;
            }
        }

        return $choices;
    }

    public function reset(): void
    {
        $this->attributes = null;
    }
}
