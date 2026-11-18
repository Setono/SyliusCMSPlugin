<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Product;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Source\Product\ProductAssociationTypeChoicesProvider;
use Sylius\Component\Product\Model\ProductAssociationType;
use Sylius\Component\Product\Model\ProductAssociationTypeInterface;
use Sylius\Component\Product\Model\ProductAssociationTypeTranslation;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * What the Associations source's association type is picked from: the store's association types, by code, named in
 * the locale asked for. See "Product sources" in docs/spec/sources.md
 */
final class ProductAssociationTypeChoicesProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_gives_the_stores_association_types_by_code_with_their_names_in_the_locale_by_name(): void
    {
        $provider = new ProductAssociationTypeChoicesProvider($this->repository([
            self::type('SIMILAR', ['en_US' => 'Similar products', 'da_DK' => 'Lignende produkter']),
            self::type('ACCESSORIES', ['en_US' => 'Accessories', 'da_DK' => 'Tilbehør']),
            self::type('UPSELL', ['en_US' => 'Upgrades', 'da_DK' => 'Opgraderinger']),
        ])->reveal());

        self::assertSame(['ACCESSORIES' => 'Accessories', 'SIMILAR' => 'Similar products', 'UPSELL' => 'Upgrades'], $provider->getChoices('en_US'));
        self::assertSame(['SIMILAR' => 'Lignende produkter', 'UPSELL' => 'Opgraderinger', 'ACCESSORIES' => 'Tilbehør'], $provider->getChoices('da_DK'));
    }

    /**
     * Asking for a translation it doesn't have would make Sylius add an empty one, which a later flush would save
     *
     * @test
     */
    public function it_names_a_type_without_a_name_in_the_locale_by_its_code_and_leaves_out_one_without_a_code(): void
    {
        $untranslated = self::type('CROSS_SELL', ['da_DK' => 'Krydssalg']);
        $provider = new ProductAssociationTypeChoicesProvider($this->repository([
            $untranslated,
            self::type(null, ['en_US' => 'Without a code']),
            self::type('SIMILAR', ['en_US' => null]),
        ])->reveal());

        self::assertSame(['CROSS_SELL' => 'CROSS_SELL', 'SIMILAR' => 'SIMILAR'], $provider->getChoices('en_US'));
        self::assertCount(1, $untranslated->getTranslations(), 'No translation was added');
    }

    /**
     * @test
     */
    public function it_reads_the_association_types_once_until_it_is_reset(): void
    {
        $repository = $this->repository([self::type('SIMILAR', ['en_US' => 'Similar products'])]);
        $repository->findBy([], ['code' => 'ASC'])->shouldBeCalledTimes(2);
        $provider = new ProductAssociationTypeChoicesProvider($repository->reveal());

        $provider->getChoices('en_US');
        $provider->getChoices('da_DK');
        $provider->reset();
        $provider->getChoices('en_US');
    }

    /**
     * @param list<ProductAssociationTypeInterface> $types
     *
     * @return \Prophecy\Prophecy\ObjectProphecy<RepositoryInterface<ProductAssociationTypeInterface>>
     */
    private function repository(array $types): \Prophecy\Prophecy\ObjectProphecy
    {
        /** @var \Prophecy\Prophecy\ObjectProphecy<RepositoryInterface<ProductAssociationTypeInterface>> $repository */
        $repository = $this->prophesize(RepositoryInterface::class);
        $repository->findBy([], ['code' => 'ASC'])->willReturn($types);

        return $repository;
    }

    /**
     * @param array<string, string|null> $names by locale
     */
    private static function type(?string $code, array $names): ProductAssociationType
    {
        $type = new ProductAssociationType();
        $type->setCode($code);
        foreach ($names as $localeCode => $name) {
            $translation = new ProductAssociationTypeTranslation();
            $translation->setLocale($localeCode);
            $translation->setName($name);
            $type->addTranslation($translation);
        }

        return $type;
    }
}
