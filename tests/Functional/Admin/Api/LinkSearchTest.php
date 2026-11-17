<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * What the link control's search picker finds: products, taxons and entries, and what each link target finds under
 * its code. See "JSON endpoints" in docs/spec/builder.md and "Field controls" in docs/spec/extending.md
 */
final class LinkSearchTest extends BuilderApiTestCase
{
    use Catalog;

    /**
     * @test
     */
    public function it_finds_products_by_name_or_code_ignoring_case_disabled_ones_too(): void
    {
        $client = self::signedIn();
        self::createProduct('T_SHIRT_RED', 'Red T-shirt');
        self::createProduct('T_SHIRT_BLUE', 'Blue T-shirt', enabled: false);
        self::createProduct('MUG', 'Mug');

        self::assertSame([
            ['ref' => 'T_SHIRT_BLUE', 'name' => 'Blue T-shirt', 'thumbnail' => null],
            ['ref' => 'T_SHIRT_RED', 'name' => 'Red T-shirt', 'thumbnail' => null],
        ], self::json($client, 'GET', '/admin/cms/api/search/product?q=t-SHIRT'));
        self::assertSame(['MUG'], array_column((array) self::json($client, 'GET', '/admin/cms/api/search/product?q=mu'), 'ref'));
        self::assertSame(['T_SHIRT_RED'], array_column((array) self::json($client, 'GET', '/admin/cms/api/search/product?q=shirt_r'), 'ref'));
        self::assertSame([], self::json($client, 'GET', '/admin/cms/api/search/product?q=bike'));
    }

    /**
     * @test
     */
    public function it_looks_products_up_by_code(): void
    {
        $client = self::signedIn();
        self::createProduct('T_SHIRT_RED', 'Red T-shirt');
        self::createProduct('MUG', 'Mug');

        self::assertSame([
            ['ref' => 'MUG', 'name' => 'Mug', 'thumbnail' => null],
            ['ref' => 'T_SHIRT_RED', 'name' => 'Red T-shirt', 'thumbnail' => null],
        ], self::json($client, 'GET', '/admin/cms/api/search/product?refs=T_SHIRT_RED,MUG,DELETED'));
    }

    /**
     * @test
     */
    public function it_finds_taxons_by_name_or_code(): void
    {
        $client = self::signedIn();
        self::createTaxon('T_SHIRTS', 'T-shirts');
        self::createTaxon('MUGS', 'Mugs', enabled: false);

        self::assertSame([['ref' => 'T_SHIRTS', 'name' => 'T-shirts', 'thumbnail' => null]], self::json($client, 'GET', '/admin/cms/api/search/taxon?q=shirt'));
        self::assertSame(['MUGS'], array_column((array) self::json($client, 'GET', '/admin/cms/api/search/taxon?q=MUGS'), 'ref'));
        self::assertSame(['MUGS', 'T_SHIRTS'], array_column((array) self::json($client, 'GET', '/admin/cms/api/search/taxon?refs=T_SHIRTS,MUGS'), 'ref'));
    }

    /**
     * @test
     */
    public function it_finds_entries_with_a_url_of_their_own_by_title_or_slug_published_or_not(): void
    {
        $client = self::signedIn();
        $about = self::createPage('About us', 'about', published: true);
        $team = self::createPage('Our team', 'about/team');
        self::announcement('About the winter sale');

        $found = (array) self::json($client, 'GET', '/admin/cms/api/search/entry?q=ABOUT');

        self::assertEqualsCanonicalizing([
            ['ref' => $about->getUuid(), 'name' => 'About us', 'thumbnail' => null],
            ['ref' => $team->getUuid(), 'name' => 'Our team', 'thumbnail' => null],
        ], $found);
        self::assertSame([$team->getUuid()], array_column((array) self::json($client, 'GET', '/admin/cms/api/search/entry?refs=' . strtoupper($team->getUuid())), 'ref'));
    }

    /**
     * @test
     */
    public function it_answers_a_link_target_s_code_with_what_the_target_finds(): void
    {
        $client = self::signedIn();

        self::assertSame([['ref' => 'aarhus', 'name' => 'Aarhus', 'thumbnail' => null]], self::json($client, 'GET', '/admin/cms/api/search/app_store?q=aar'));
        self::assertSame([], self::json($client, 'GET', '/admin/cms/api/search/app_store?refs=aarhus'));
        self::assertSame([], self::json($client, 'GET', '/admin/cms/api/search/url?q=https'));
    }

    private static function signedIn(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    private static function announcement(string $title): void
    {
        /** @var FactoryInterface<Announcement> $factory */
        $factory = self::getContainer()->get('app.factory.announcement');
        $announcement = $factory->createNew();
        $announcement->setTitle($title);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('app.manager.announcement');
        $manager->persist($announcement);
        $manager->flush();
    }
}
