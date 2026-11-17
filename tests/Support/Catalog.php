<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonImageInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Product\Model\ProductAssociationInterface;
use Sylius\Component\Product\Model\ProductAssociationTypeInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helpers for functional and UI tests that need products and taxons, which the test fixtures don't have. They're made
 * with Sylius's example factories, translated into every locale the store has, with a slug made from the name
 */
trait Catalog
{
    abstract protected static function getContainer(): ContainerInterface;

    /**
     * @param list<string> $channels the codes of its channels
     */
    protected static function createProduct(string $code, string $name, bool $enabled = true, array $channels = ['WEB']): ProductInterface
    {
        /** @var ExampleFactoryInterface $factory */
        $factory = self::getContainer()->get('sylius.fixture.example_factory.product');
        $product = $factory->create([
            'code' => $code,
            'name' => $name,
            'enabled' => $enabled,
            'channels' => $channels,
            'main_taxon' => null,
            'taxons' => [],
            'images' => [],
        ]);
        self::assertInstanceOf(ProductInterface::class, $product);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.product');
        $manager->persist($product);
        $manager->flush();

        return $product;
    }

    /**
     * Adds a translation to the product, in a locale it has none in
     */
    protected static function translateProduct(ProductInterface $product, string $localeCode, string $name, string $slug): void
    {
        /** @var FactoryInterface<ProductTranslationInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.product_translation');
        $translation = $factory->createNew();
        $translation->setLocale($localeCode);
        $translation->setName($name);
        $translation->setSlug($slug);
        $product->addTranslation($translation);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.product');
        $manager->flush();
    }

    protected static function createTaxon(string $code, string $name, bool $enabled = true): TaxonInterface
    {
        /** @var ExampleFactoryInterface $factory */
        $factory = self::getContainer()->get('sylius.fixture.example_factory.taxon');
        $taxon = $factory->create(['code' => $code, 'name' => $name]);
        self::assertInstanceOf(TaxonInterface::class, $taxon);
        $taxon->setEnabled($enabled);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.taxon');
        $manager->persist($taxon);
        $manager->flush();

        return $taxon;
    }

    /**
     * A taxon below another, at the end of its children
     */
    protected static function createChildTaxon(TaxonInterface $parent, string $code, string $name, bool $enabled = true): TaxonInterface
    {
        /** @var ExampleFactoryInterface $factory */
        $factory = self::getContainer()->get('sylius.fixture.example_factory.taxon');
        $taxon = $factory->create(['code' => $code, 'name' => $name]);
        self::assertInstanceOf(TaxonInterface::class, $taxon);
        $taxon->setEnabled($enabled);
        $parent->addChild($taxon);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.taxon');
        $manager->persist($taxon);
        $manager->flush();

        return $taxon;
    }

    /**
     * Puts the product in the taxon, at the position, or at the end of the taxon's products
     */
    protected static function placeInTaxon(ProductInterface $product, TaxonInterface $taxon, ?int $position = null): void
    {
        /** @var FactoryInterface<ProductTaxonInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.product_taxon');
        $productTaxon = $factory->createNew();
        $productTaxon->setTaxon($taxon);
        if (null !== $position) {
            $productTaxon->setPosition($position);
        }
        $product->addProductTaxon($productTaxon);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.product');
        $manager->flush();
    }

    /**
     * Makes the taxon the product's main taxon. It doesn't put the product in it: placeInTaxon() does
     */
    protected static function setMainTaxon(ProductInterface $product, TaxonInterface $taxon): void
    {
        $product->setMainTaxon($taxon);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.product');
        $manager->flush();
    }

    /**
     * An association type, named in en_US, the locale the test fixtures have
     */
    protected static function createAssociationType(string $code, string $name): ProductAssociationTypeInterface
    {
        /** @var FactoryInterface<ProductAssociationTypeInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.product_association_type');
        $type = $factory->createNew();
        $type->setCurrentLocale('en_US');
        $type->setFallbackLocale('en_US');
        $type->setCode($code);
        $type->setName($name);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.product_association_type');
        $manager->persist($type);
        $manager->flush();

        return $type;
    }

    /**
     * Associates the products with the product, as its association of the type, which Sylius allows one of per type
     *
     * @param list<ProductInterface> $products
     */
    protected static function associate(ProductInterface $product, ProductAssociationTypeInterface $type, array $products): void
    {
        /** @var FactoryInterface<ProductAssociationInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.product_association');
        $association = $factory->createNew();
        $association->setType($type);
        foreach ($products as $associated) {
            $association->addAssociatedProduct($associated);
        }
        $product->addAssociation($association);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.product');
        $manager->flush();
    }

    /**
     * Sets the price of each of the product's variants in the channel, or, for a variant of the product with no such
     * price, adds it. The example factory gives the product one variant with a random price in every channel
     */
    protected static function priceProduct(ProductInterface $product, int $price, string $channelCode = 'WEB'): void
    {
        foreach ($product->getVariants() as $variant) {
            self::assertInstanceOf(ProductVariantInterface::class, $variant);
            self::priceVariant($variant, $price, $channelCode);
        }

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.product');
        $manager->flush();
    }

    /**
     * Adds a variant to the product with a price in the channel
     */
    protected static function addProductVariant(ProductInterface $product, string $code, int $price, bool $enabled = true, string $channelCode = 'WEB'): ProductVariantInterface
    {
        /** @var FactoryInterface<ProductVariantInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.product_variant');
        $variant = $factory->createNew();
        $variant->setCode($code);
        $variant->setEnabled($enabled);
        self::priceVariant($variant, $price, $channelCode);
        $product->addVariant($variant);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.product');
        $manager->flush();

        return $variant;
    }

    /**
     * Adds an image to the taxon, at a path in Sylius's image storage. Its file needn't exist: Liip makes the
     * thumbnail's URL without it
     */
    protected static function addTaxonImage(TaxonInterface $taxon, string $path, string $type = 'main'): void
    {
        /** @var FactoryInterface<TaxonImageInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.taxon_image');
        $image = $factory->createNew();
        $image->setPath($path);
        $image->setType($type);
        $taxon->addImage($image);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.taxon');
        $manager->flush();
    }

    private static function priceVariant(ProductVariantInterface $variant, int $price, string $channelCode): void
    {
        $channelPricing = self::channelPricing($variant, $channelCode);
        if (null === $channelPricing) {
            /** @var FactoryInterface<ChannelPricingInterface> $factory */
            $factory = self::getContainer()->get('sylius.factory.channel_pricing');
            $channelPricing = $factory->createNew();
            $channelPricing->setChannelCode($channelCode);
            $variant->addChannelPricing($channelPricing);
        }

        $channelPricing->setPrice($price);
    }

    /**
     * Takes away the price of each of the product's variants in the channel, so it can't be bought there. The channel
     * pricing stays, without a price, since Sylius logs prices against it
     */
    protected static function unpriceProduct(ProductInterface $product, string $channelCode = 'WEB'): void
    {
        foreach ($product->getVariants() as $variant) {
            self::assertInstanceOf(ProductVariantInterface::class, $variant);
            self::channelPricing($variant, $channelCode)?->setPrice(null);
        }

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.product');
        $manager->flush();
    }

    private static function channelPricing(ProductVariantInterface $variant, string $channelCode): ?ChannelPricingInterface
    {
        foreach ($variant->getChannelPricings() as $channelPricing) {
            if ($channelPricing->getChannelCode() === $channelCode) {
                return $channelPricing;
            }
        }

        return null;
    }
}
