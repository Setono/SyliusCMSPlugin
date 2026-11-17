<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding\Product;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Binding\Product\ProductAttributeChoicesProvider;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeTranslation;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * What the Product attribute binding's attribute is picked from, and what the product_attribute search searches: the
 * store's product attributes, by code, named in the locale asked for. See "Shipped bindable properties" in
 * docs/spec/slots.md
 */
final class ProductAttributeChoicesProviderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_gives_the_store_s_product_attributes_by_code_with_their_names_in_the_locale_in_the_order_of_their_positions(): void
    {
        $provider = new ProductAttributeChoicesProvider($this->repository([
            self::attribute('material', ['en_US' => 'Material', 'da_DK' => 'Materiale']),
            self::attribute('fit', ['en_US' => 'Fit', 'da_DK' => 'Pasform']),
        ])->reveal());

        self::assertSame(['material' => 'Material', 'fit' => 'Fit'], $provider->getChoices('en_US'));
        self::assertSame(['material' => 'Materiale', 'fit' => 'Pasform'], $provider->getChoices('da_DK'));
    }

    /**
     * As an association type is. Asking for a translation it doesn't have would make Sylius add an empty one, which a
     * later flush would save
     *
     * @test
     */
    public function it_names_an_attribute_without_a_name_in_the_locale_by_its_code_and_leaves_out_one_without_a_code(): void
    {
        $untranslated = self::attribute('care', ['da_DK' => 'Vask']);
        $provider = new ProductAttributeChoicesProvider($this->repository([
            $untranslated,
            self::attribute(null, ['en_US' => 'Without a code']),
            self::attribute('fit', ['en_US' => '']),
        ])->reveal());

        self::assertSame(['care' => 'care', 'fit' => 'fit'], $provider->getChoices('en_US'));
        self::assertCount(1, $untranslated->getTranslations(), 'No translation was added');
    }

    /**
     * @test
     */
    public function it_reads_the_attributes_once_until_it_is_reset(): void
    {
        $repository = $this->repository([self::attribute('material', ['en_US' => 'Material'])]);
        $repository->findBy([], ['position' => 'ASC'])->shouldBeCalledTimes(2);
        $provider = new ProductAttributeChoicesProvider($repository->reveal());

        $provider->getChoices('en_US');
        $provider->getChoices('da_DK');
        $provider->reset();
        $provider->getChoices('en_US');
    }

    /**
     * @param list<ProductAttributeInterface> $attributes in the order of their positions
     *
     * @return ObjectProphecy<RepositoryInterface<ProductAttributeInterface>>
     */
    private function repository(array $attributes): ObjectProphecy
    {
        /** @var ObjectProphecy<RepositoryInterface<ProductAttributeInterface>> $repository */
        $repository = $this->prophesize(RepositoryInterface::class);
        $repository->findBy([], ['position' => 'ASC'])->willReturn($attributes);

        return $repository;
    }

    /**
     * @param array<string, string> $names by locale
     */
    private static function attribute(?string $code, array $names): ProductAttributeInterface
    {
        $attribute = new ProductAttribute();
        $attribute->setCode($code);
        foreach ($names as $locale => $name) {
            $translation = new ProductAttributeTranslation();
            $translation->setLocale($locale);
            $translation->setName($name);
            $attribute->addTranslation($translation);
        }

        return $attribute;
    }
}
