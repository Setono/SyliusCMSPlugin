<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A page can be a channel's homepage, shown at the channel's root URL in place of the store's own homepage. The test
 * app's channel WEB is on localhost, with en_US as its default locale, and Sylius's homepage has the channel's name as
 * its title. See "Homepage" and "Rendering the homepage" in docs/spec/urls-and-seo.md
 */
final class HomepageTest extends WebTestCase
{
    use Entries;

    private const STORE_HOMEPAGE_TITLE = 'Setono CMS Test Store';

    /**
     * @test
     */
    public function it_renders_the_channels_homepage_in_its_layout_at_the_channels_root_url(): void
    {
        $client = self::createClient();
        $page = self::createPage('Welcome', 'welcome', 'Hello shoppers', published: true);
        self::setHomepageOf($page, 'WEB');

        $crawler = $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello shoppers');
        self::assertSelectorTextSame('h1', 'Welcome', 'The page renders in its layout, Standard, which shows its title');
        self::assertSame('Welcome | Setono CMS Test Store', $crawler->filter('head title')->text(), 'Its title, followed by the channel\'s name');
    }

    /**
     * @test
     */
    public function it_titles_the_homepage_with_its_meta_title_exactly(): void
    {
        $client = self::createClient();
        $page = self::createPage('Welcome', 'welcome', 'Hello shoppers', published: true);
        self::setHomepageOf($page, 'WEB');
        self::reloadPage($page)->getTranslation('en_US')->setMetaTitle('Fashion for everyone');
        self::pageManager()->flush();

        $crawler = $client->request('GET', '/en_US/');

        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello shoppers');
        self::assertSame('Fashion for everyone', $crawler->filter('head title')->text());
    }

    /**
     * @test
     */
    public function it_shows_the_stores_own_homepage_in_a_channel_without_one(): void
    {
        $client = self::createClient();
        self::createPage('Welcome', 'welcome', 'Hello shoppers', published: true);

        $crawler = $client->request('GET', '/en_US/');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::STORE_HOMEPAGE_TITLE, $crawler->filter('head title')->text());
        self::assertSelectorNotExists('.cms-heading');
    }

    /**
     * The homepage never answers 404: when the shopper can't see the page, Sylius's own homepage renders instead
     *
     * @test
     */
    public function it_falls_back_to_the_stores_own_homepage_for_a_disabled_homepage(): void
    {
        $client = self::createClient();
        $page = self::createPage('Welcome', 'welcome', 'Hello shoppers', published: true);
        self::setHomepageOf($page, 'WEB');
        $page = self::reloadPage($page);
        $page->setEnabled(false);
        self::pageManager()->flush();

        $crawler = $client->request('GET', '/en_US/');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::STORE_HOMEPAGE_TITLE, $crawler->filter('head title')->text());
        self::assertSelectorNotExists('.cms-heading');
    }

    /**
     * @test
     */
    public function it_falls_back_to_the_stores_own_homepage_for_a_never_published_homepage(): void
    {
        $client = self::createClient();
        self::setHomepageOf(self::createPage('Welcome', 'welcome', 'Hello shoppers'), 'WEB');

        $crawler = $client->request('GET', '/en_US/');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::STORE_HOMEPAGE_TITLE, $crawler->filter('head title')->text());
    }

    /**
     * A page shows only in locales where it has a translation, so its channel's other locales show the store's homepage
     *
     * @test
     */
    public function it_shows_the_stores_own_homepage_in_a_locale_the_homepage_has_no_translation_in(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        self::setHomepageOf(self::createPage('Welcome', 'welcome', 'Hello shoppers', published: true), 'WEB');

        $crawler = $client->request('GET', '/da_DK/');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorNotExists('.cms-tree');
        self::assertCount(0, $crawler->filter('link[rel="canonical"]'));
    }

    /**
     * @test
     */
    public function it_does_not_show_the_homepage_of_another_channel(): void
    {
        $client = self::createClient();
        self::createChannel('B2B');
        self::setHomepageOf(self::createPage('Wholesale', 'wholesale', 'Hello traders', published: true), 'B2B');

        $crawler = $client->request('GET', '/en_US/');

        self::assertSame(self::STORE_HOMEPAGE_TITLE, $crawler->filter('head title')->text());
        self::assertSelectorNotExists('.cms-heading');
    }

    /**
     * Links to the page point straight to the channel's root, so the page's own URL redirects there, keeping the query
     * string
     *
     * @test
     */
    public function it_answers_the_pages_own_url_with_301_to_the_channels_root(): void
    {
        $client = self::createClient();
        self::setHomepageOf(self::createPage('Welcome', 'welcome', 'Hello shoppers', published: true), 'WEB');

        $client->request('GET', '/en_US/welcome?utm_source=newsletter');

        self::assertResponseRedirects('/en_US/?utm_source=newsletter', 301);
        $client->followRedirect();
        self::assertSelectorTextSame('.cms-heading', 'Hello shoppers');
    }

    /**
     * @test
     */
    public function it_answers_an_old_slug_of_the_homepage_with_301_straight_to_the_channels_root(): void
    {
        $client = self::createClient();
        $page = self::createPage('Welcome', 'welcome', 'Hello shoppers', published: true);
        self::saveChanges($page, [['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => 'home']]);
        self::publishEntry(self::reloadPage($page));
        self::setHomepageOf(self::reloadPage($page), 'WEB');

        $client->request('GET', '/en_US/welcome');

        self::assertResponseRedirects('/en_US/', 301);
    }

    /**
     * @test
     */
    public function it_does_not_redirect_the_pages_own_url_in_a_channel_it_is_not_the_homepage_of(): void
    {
        $client = self::createClient();
        self::createChannel('B2B');
        self::setHomepageOf(self::createPage('Welcome', 'welcome', 'Hello shoppers', published: true), 'B2B');

        $client->request('GET', '/en_US/welcome');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.cms-heading', 'Hello shoppers');
    }

    /**
     * An unavailable page's URL answers 404, homepage or not, and its channel's root shows the store's homepage
     *
     * @test
     */
    public function it_answers_404_to_an_unavailable_homepages_own_url(): void
    {
        $client = self::createClient();
        self::setHomepageOf(self::createPage('Welcome', 'welcome', 'Hello shoppers'), 'WEB');

        $client->request('GET', '/en_US/welcome');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_makes_the_channels_root_the_homepages_canonical_url_and_hreflang_links_in_each_locale(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('Welcome', 'welcome', 'Hello shoppers', published: true);
        self::addTranslation($page, 'da_DK', 'Velkommen', 'velkommen');
        self::setHomepageOf(self::reloadPage($page), 'WEB');

        $head = $client->request('GET', '/en_US/')->filter('head');

        self::assertSame('http://localhost/en_US/', self::attribute($head, 'link[rel="canonical"]', 'href'));
        self::assertSame([
            'da-DK' => 'http://localhost/da_DK/',
            'en-US' => 'http://localhost/en_US/',
            'x-default' => 'http://localhost/en_US/',
        ], self::alternates($head));

        $head = $client->request('GET', '/da_DK/')->filter('head');

        self::assertSame('Velkommen | Setono CMS Test Store', $head->filter('title')->text());
        self::assertSame('http://localhost/da_DK/', self::attribute($head, 'link[rel="canonical"]', 'href'));
    }

    /**
     * The builder previews a homepage at its channel's root URL, with its working copy, whatever its own visibility
     *
     * @test
     */
    public function it_shows_the_homepages_working_copy_at_the_channels_root_in_the_builders_preview(): void
    {
        $client = self::createClient();
        $page = self::createPage('Welcome', 'welcome', 'Hello shoppers');
        self::setHomepageOf($page, 'WEB');

        $client->request('GET', '/en_US/?' . PreviewContext::QUERY_PARAMETER . '=' . self::previewToken($page));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.cms-heading', 'Hello shoppers');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
    }

    private static function pageManager(): ObjectManager
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');

        return $manager;
    }

    private static function attribute(Crawler $head, string $selector, string $attribute): ?string
    {
        $elements = $head->filter($selector);
        self::assertCount(1, $elements, sprintf('The head has one %s', $selector));

        return $elements->attr($attribute);
    }

    /**
     * @return array<string, string> hreflang => URL
     */
    private static function alternates(Crawler $head): array
    {
        $alternates = [];
        foreach ($head->filter('link[rel="alternate"][hreflang]') as $link) {
            self::assertInstanceOf(\DOMElement::class, $link);
            $alternates[$link->getAttribute('hreflang')] = $link->getAttribute('href');
        }

        return $alternates;
    }
}
