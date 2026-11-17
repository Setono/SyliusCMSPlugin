<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\ProfiledQueries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The Button block: a Link field shown as a button, labelled with the link's label or the name of what it leads to.
 * A link that doesn't resolve, to something the shopper may not see or that was deleted, renders no button. See
 * "Button" in docs/spec/blocks.md, "Links" in docs/spec/content-model.md and "References to unavailable entries" in
 * docs/spec/localization.md
 */
final class ButtonTest extends WebTestCase
{
    use Catalog;
    use Entries;
    use ProfiledQueries;

    private const BUTTON = '0193a5be-0000-7000-8000-0000000000b1';

    private const OTHER_BUTTON = '0193a5be-0000-7000-8000-0000000000b2';

    /**
     * @test
     */
    public function it_shows_a_button_with_the_product_s_name_that_leads_to_the_product_page_when_its_label_is_empty(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT_RED', 'Red T-shirt');
        self::createPageWithBlocks('Shop now', 'shop-now', [self::button(self::BUTTON, ['target' => 'product', 'ref' => 'T_SHIRT_RED', 'anchor' => null, 'label' => []], 'secondary')], published: true);

        $crawler = $client->request('GET', '/en_US/shop-now');

        self::assertResponseIsSuccessful();
        $button = $crawler->filter('.cms-tree > a.cms-button');
        self::assertSame('cms-button cms-button--secondary', $button->attr('class'));
        self::assertSame('Red T-shirt', $button->text());
        self::assertSame('/en_US/products/red-t-shirt', $button->attr('href'));
    }

    /**
     * @test
     */
    public function it_shows_the_label_of_the_shopper_s_locale_and_leads_to_the_anchor(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'de_DE');
        $page = self::createPageWithBlocks('Shop now', 'shop-now', [self::button(self::BUTTON, ['target' => 'url', 'ref' => ['en_US' => '/en_US/sale'], 'anchor' => 'shirts', 'label' => ['en_US' => 'See the sale', 'de_DE' => 'Zum Sale']])], published: true);
        self::addTranslation($page, 'de_DE', 'Jetzt kaufen', 'jetzt-kaufen');

        $english = $client->request('GET', '/en_US/shop-now')->filter('.cms-tree > a.cms-button');
        self::assertSame('See the sale', $english->text());
        self::assertSame('/en_US/sale#shirts', $english->attr('href'));
        self::assertSame('cms-button', $english->attr('class'));

        $german = $client->request('GET', '/de_DE/jetzt-kaufen')->filter('.cms-tree > a.cms-button');
        self::assertSame('Zum Sale', $german->text());
        self::assertSame('/en_US/sale#shirts', $german->attr('href'), 'The URL falls back to the store\'s fallback locale');
    }

    /**
     * @test
     */
    public function it_leads_a_url_link_to_the_url_of_the_shopper_s_locale(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'de_DE');
        $page = self::createPageWithBlocks('Size guide', 'size-guide', [self::button(self::BUTTON, ['target' => 'url', 'ref' => ['en_US' => 'https://example.com/en/sizes', 'de_DE' => 'https://example.com/de/groessen'], 'anchor' => null, 'label' => ['en_US' => 'Sizes']])], published: true);
        self::addTranslation($page, 'de_DE', 'Größen', 'groessen');

        self::assertSame('https://example.com/en/sizes', $client->request('GET', '/en_US/size-guide')->filter('.cms-tree > a.cms-button')->attr('href'));
        self::assertSame('https://example.com/de/groessen', $client->request('GET', '/de_DE/groessen')->filter('.cms-tree > a.cms-button')->attr('href'));
    }

    /**
     * @test
     */
    public function it_shows_no_button_for_a_link_to_a_disabled_product(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT_RED', 'Red T-shirt', enabled: false);
        self::createPageWithBlocks('Shop now', 'shop-now', [self::button(self::BUTTON, ['target' => 'product', 'ref' => 'T_SHIRT_RED', 'label' => ['en_US' => 'Buy it']])], published: true);

        $crawler = $client->request('GET', '/en_US/shop-now');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.cms-tree .cms-button'));
    }

    /**
     * @test
     */
    public function it_shows_no_button_for_a_link_to_a_product_that_was_deleted(): void
    {
        $client = self::createClient();
        $product = self::createProduct('T_SHIRT_RED', 'Red T-shirt');
        self::createPageWithBlocks('Shop now', 'shop-now', [self::button(self::BUTTON, ['target' => 'product', 'ref' => 'T_SHIRT_RED', 'label' => ['en_US' => 'Buy it']])], published: true);
        self::entityManager()->remove($product);
        self::entityManager()->flush();

        $crawler = $client->request('GET', '/en_US/shop-now');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.cms-tree .cms-button'));
    }

    /**
     * @test
     */
    public function it_flags_a_link_to_a_product_that_was_deleted_as_a_broken_reference_on_the_overview_page(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $product = self::createProduct('T_SHIRT_RED', 'Red T-shirt');
        $page = self::createPageWithBlocks('Shop now', 'shop-now', [self::button(self::BUTTON, ['target' => 'product', 'ref' => 'T_SHIRT_RED', 'label' => []])], published: true);
        self::assertEquals(new ProblemCount(), self::storedCount($page));

        self::entityManager()->remove($product);
        self::entityManager()->flush();

        self::assertEquals(new ProblemCount(1, 0), self::storedCount($page), 'Deleting the product counts the entries that link to it again');

        $problems = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()))->filter('[data-section="problems"] [data-problem]');
        self::assertSame(
            [['broken_reference', 'error', self::BUTTON, 'Link in the Button block refers to a product that was deleted, so it isn\'t shown.']],
            $problems->each(static fn (Crawler $problem): array => [$problem->attr('data-problem'), $problem->attr('data-severity'), $problem->attr('data-block'), $problem->filter('.description')->text()]),
        );
    }

    /**
     * @test
     */
    public function it_does_not_flag_a_link_to_a_disabled_product_or_an_unpublished_entry_as_a_broken_reference(): void
    {
        self::createClient();
        self::createProduct('T_SHIRT_RED', 'Red T-shirt', enabled: false);
        $draft = self::createPage('Our team', 'team');
        $page = self::createPageWithBlocks('Shop now', 'shop-now', [
            self::button(self::BUTTON, ['target' => 'product', 'ref' => 'T_SHIRT_RED', 'label' => []]),
            self::button(self::OTHER_BUTTON, ['target' => 'entry', 'ref' => $draft->getUuid(), 'label' => []]),
        ], published: true);

        self::assertEquals(new ProblemCount(), self::storedCount($page));
    }

    /**
     * @test
     */
    public function it_leads_a_link_to_an_entry_to_its_url_and_shows_no_button_for_one_that_is_not_published(): void
    {
        $client = self::createClient();
        $about = self::createPage('About us', 'about', published: true);
        $draft = self::createPage('Our team', 'team');
        self::createPageWithBlocks('Start', 'start', [
            self::button(self::BUTTON, ['target' => 'entry', 'ref' => $about->getUuid(), 'label' => []]),
            self::button(self::OTHER_BUTTON, ['target' => 'entry', 'ref' => $draft->getUuid(), 'label' => ['en_US' => 'Meet the team']]),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/start');

        self::assertResponseIsSuccessful();
        self::assertSame([['About us', '/en_US/about']], $crawler->filter('.cms-tree .cms-button')->each(static fn (Crawler $button): array => [$button->text(), $button->attr('href')]));
    }

    /**
     * @test
     */
    public function it_flags_a_link_to_an_entry_that_is_not_published_in_the_builder_s_preview_and_shows_shoppers_nothing(): void
    {
        $client = self::createClient();
        $draft = self::createPage('Our team', 'team');
        $page = self::createPageWithBlocks('Start', 'start', [self::button(self::BUTTON, ['target' => 'entry', 'ref' => $draft->getUuid(), 'label' => ['en_US' => 'Meet the team']])], published: true);

        $preview = $client->request('GET', '/en_US/start?_cms_preview=' . self::previewToken($page));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $preview->filter('.cms-button'));
        $notice = $preview->filter('.cms-tree [data-cms-unresolved-link="link"]');
        self::assertSame('Link isn\'t shown here: what it links to isn\'t visible in this channel and locale, or was deleted.', $notice->text());
        self::assertStringContainsString(sprintf('<!--cms:block %s button--><div data-cms-unresolved-link="link"', self::BUTTON), (string) $client->getResponse()->getContent());

        $shop = $client->request('GET', '/en_US/start');
        self::assertCount(0, $shop->filter('.cms-button'));
        self::assertCount(0, $shop->filter('[data-cms-unresolved-link]'));
    }

    /**
     * @test
     */
    public function it_shows_a_link_to_an_entry_in_the_preview_of_the_channel_and_locale_where_it_is_visible(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'de_DE');
        $about = self::createPage('About us', 'about', published: true);
        $page = self::createPageWithBlocks('Start', 'start', [self::button(self::BUTTON, ['target' => 'entry', 'ref' => $about->getUuid(), 'label' => []])], published: true);
        self::addTranslation($page, 'de_DE', 'Start', 'start');

        $english = $client->request('GET', '/en_US/start?_cms_preview=' . self::previewToken($page));
        self::assertSame('About us', $english->filter('.cms-tree .cms-button')->text());
        self::assertCount(0, $english->filter('[data-cms-unresolved-link]'));

        $german = $client->request('GET', '/de_DE/start?_cms_preview=' . self::previewToken($page, locale: 'de_DE'));
        self::assertCount(0, $german->filter('.cms-tree .cms-button'));
        self::assertCount(1, $german->filter('.cms-tree [data-cms-unresolved-link="link"]'), 'About us isn\'t translated into German');
    }

    /**
     * @test
     */
    public function it_leads_a_link_to_a_channel_s_homepage_to_the_channel_s_root(): void
    {
        $client = self::createClient();
        $home = self::createPage('Home', 'home', published: true);
        self::setHomepageOf($home, 'WEB');
        self::createPageWithBlocks('Start', 'start', [self::button(self::BUTTON, ['target' => 'entry', 'ref' => $home->getUuid(), 'label' => ['en_US' => 'Home']])], published: true);

        self::assertSame('/en_US/', $client->request('GET', '/en_US/start')->filter('.cms-tree > a.cms-button')->attr('href'));
    }

    /**
     * @test
     */
    public function it_leads_a_link_to_a_taxon_to_its_product_listing(): void
    {
        $client = self::createClient();
        self::createTaxon('T_SHIRTS', 'T-shirts');
        self::createPageWithBlocks('Start', 'start', [self::button(self::BUTTON, ['target' => 'taxon', 'ref' => 'T_SHIRTS', 'label' => []])], published: true);

        $button = $client->request('GET', '/en_US/start')->filter('.cms-tree > a.cms-button');

        self::assertSame('T-shirts', $button->text());
        self::assertSame('/en_US/taxons/t-shirts', $button->attr('href'));
    }

    /**
     * @test
     */
    public function it_loads_the_products_of_every_link_on_the_page_with_one_query(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT_RED', 'Red T-shirt');
        self::createProduct('MUG', 'Mug');
        self::createPageWithBlocks('Start', 'start', [
            self::button(self::BUTTON, ['target' => 'product', 'ref' => 'T_SHIRT_RED', 'label' => []]),
            self::button(self::OTHER_BUTTON, ['target' => 'product', 'ref' => 'MUG', 'label' => []]),
        ], published: true);
        // The queries of a request are profiled from a kernel that's booted afresh, which comes after the first request
        $client->request('GET', '/');

        $client->enableProfiler();
        $crawler = $client->request('GET', '/en_US/start');

        self::assertSame(['Red T-shirt', 'Mug'], $crawler->filter('.cms-tree .cms-button')->each(static fn (Crawler $button): string => $button->text()));
        self::assertCount(1, self::queriesOf($client, 'sylius_product '), 'One query loads the products');
    }

    /**
     * @param array<string, mixed> $link
     */
    private static function button(string $key, array $link, ?string $style = null): StoredBlock
    {
        return new StoredBlock($key, 'button', 1, $style, ['link' => $link]);
    }
}
