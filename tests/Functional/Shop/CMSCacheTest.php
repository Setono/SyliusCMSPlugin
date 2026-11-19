<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Psr\Cache\CacheItemPoolInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\CMSCacheKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tests\Support\ProfiledQueries;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The caching cookbook's cache in the test application, docs/cookbook/caching.md. See "No caching in v2.0" in
 * docs/spec/rendering.md and "Rendering services" in docs/spec/extending.md
 */
final class CMSCacheTest extends WebTestCase
{
    use Entries;
    use Placements;
    use ProfiledQueries;

    private const FREE_SHIPPING = '0193a5be-0000-7000-8000-000000000001';

    private const OPENING_HOURS = '0193a5be-0000-7000-8000-000000000002';

    protected static function getKernelClass(): string
    {
        return CMSCacheKernel::class;
    }

    /**
     * @test
     */
    public function it_shows_a_slot_s_placements_from_the_cache_until_placements_change(): void
    {
        $client = self::createClient();
        self::clearCMSCache();
        $freeShipping = self::place(self::createSnippet('Free shipping', [self::heading(self::FREE_SHIPPING, 'Free shipping')], published: true), 'shop.footer');
        $openingHours = self::createSnippet('Opening hours', [self::heading(self::OPENING_HOURS, 'Opening hours')], published: true);

        self::assertSame(['Free shipping'], self::footerHeadings($client));

        // Placed behind the placement editor's back, so without PlacementsChanged: the page reads no placements, and
        // shows those in the cache
        $placedUnseen = self::placeUnchecked($openingHours, 'shop.footer');
        $client->enableProfiler();
        self::assertSame(['Free shipping'], self::footerHeadings($client));
        self::assertCount(0, self::queriesOf($client, 'setono_sylius_cms__placement'));

        // Reordering dispatches PlacementsChanged
        self::placementEditor()->reorder('shop.footer', [$placedUnseen->getUuid(), $freeShipping->getUuid()]);
        self::assertSame(['Opening hours', 'Free shipping'], self::footerHeadings($client));
    }

    /**
     * @test
     */
    public function it_finds_an_entry_s_url_in_the_cache_until_a_publish_changes_it(): void
    {
        $client = self::createClient();
        self::clearCMSCache();
        $page = self::createPage('About us', 'about', 'Hello', published: true);

        $client->enableProfiler();
        $client->request('GET', '/en_US/about');
        self::assertResponseIsSuccessful();
        self::assertCount(1, self::queriesOf($client, 'setono_sylius_cms__old_slug'), 'The URL is looked up');

        $client->enableProfiler();
        $client->request('GET', '/en_US/about');
        self::assertResponseIsSuccessful();
        self::assertCount(0, self::queriesOf($client, 'setono_sylius_cms__old_slug'), 'The URL is found in the cache');

        // The publish dispatches EntryPublished, and the old URL redirects to the new one
        $page = self::reloadPage($page);
        self::saveChanges($page, [['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => 'about-us']]);
        self::publishEntry($page);
        $client->request('GET', '/en_US/about');
        self::assertResponseRedirects('/en_US/about-us', 301);
    }

    /**
     * A URL no entry claims is looked up every time, so an entry created at it is found at once
     *
     * @test
     */
    public function it_looks_a_url_no_entry_claims_up_every_time(): void
    {
        $client = self::createClient();
        self::clearCMSCache();

        $client->request('GET', '/en_US/opening-hours');
        self::assertResponseStatusCodeSame(404);

        self::createPage('Opening hours', 'opening-hours', 'Open every day', published: true);
        $client->request('GET', '/en_US/opening-hours');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.cms-heading', 'Open every day');
    }

    /**
     * @return list<string>
     */
    private static function footerHeadings(KernelBrowser $client): array
    {
        $crawler = $client->request('GET', '/en_US/');
        self::assertResponseIsSuccessful();

        /** @var list<string> $headings */
        $headings = $crawler->filter('.cms-slot--shop-footer .cms-heading')->each(static fn (Crawler $heading): string => $heading->text());

        return $headings;
    }

    /**
     * The cache outlives the database transaction each test is rolled back in
     */
    private static function clearCMSCache(): void
    {
        $cache = self::getContainer()->get(CMSCacheKernel::POOL);
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        self::assertTrue($cache->clear());
    }
}
