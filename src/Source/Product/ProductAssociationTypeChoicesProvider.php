<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Product;

use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Sylius\Component\Product\Model\ProductAssociationTypeInterface;
use Sylius\Component\Product\Model\ProductAssociationTypeTranslationInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The store's association types, by code, with their names in the locale asked for, or their codes where they have no
 * name in it, ordered by that name, for the Associations source's association type. They're read once per request
 */
final class ProductAssociationTypeChoicesProvider implements ChoicesProviderInterface, ResetInterface
{
    /** @var array<ProductAssociationTypeInterface>|null */
    private ?array $types = null;

    /**
     * @param RepositoryInterface<ProductAssociationTypeInterface> $associationTypeRepository
     */
    public function __construct(private readonly RepositoryInterface $associationTypeRepository)
    {
    }

    public function getChoices(string $localeCode): array
    {
        $this->types ??= $this->associationTypeRepository->findBy([], ['code' => 'ASC']);

        $choices = [];
        foreach ($this->types as $type) {
            $code = $type->getCode();
            if (null !== $code) {
                $choices[$code] = self::name($type, $localeCode) ?? $code;
            }
        }

        uasort($choices, strnatcasecmp(...));

        return $choices;
    }

    /**
     * Its name in the locale. Not through getTranslation(), which adds an empty translation in a locale the type has
     * none in, and a later flush would save it
     */
    private static function name(ProductAssociationTypeInterface $type, string $localeCode): ?string
    {
        $translation = $type->getTranslations()->get($localeCode);

        return $translation instanceof ProductAssociationTypeTranslationInterface ? $translation->getName() : null;
    }

    public function reset(): void
    {
        $this->types = null;
    }
}
