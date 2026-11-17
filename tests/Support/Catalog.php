<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\TaxonImageInterface;
use Sylius\Component\Core\Model\TaxonInterface;
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
}
