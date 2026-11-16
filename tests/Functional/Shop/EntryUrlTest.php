<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EntryUrlTest extends WebTestCase
{
    use Customers;
    use Entries;

    /**
     * @test
     */
    public function a_published_page_shows_its_heading_at_its_url(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertResponseStatusCodeSame(200);
        self::assertRouteSame('setono_sylius_cms_shop_entry');
        self::assertSame('<h2 class="cms-heading">Hello</h2>', $crawler->filter('.cms-tree .cms-heading')->outerHtml());
        self::assertSelectorTextSame('h1', 'About us');
    }

    /**
     * @test
     */
    public function a_page_renders_inside_the_shop_layout(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $client->request('GET', '/en_US/about-us');

        self::assertSelectorExists('#footer', 'The page must render inside @SyliusShop/layout.html.twig');
    }

    /**
     * @test
     */
    public function a_slug_with_slashes_is_one_url(): void
    {
        $client = self::createClient();
        self::createPage('Team', 'about/team', 'Our team', published: true);

        $client->request('GET', '/en_US/about/team');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-heading', 'Our team');
    }

    /**
     * @test
     */
    public function a_never_published_page_answers_404(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us');

        self::assertResponseStatusCodeSame(404);
        self::assertRouteSame('setono_sylius_cms_shop_entry', [], 'The URL exists, so the entry route claims it and its controller answers 404');
    }

    /**
     * @test
     */
    public function a_url_no_entry_claims_falls_through_to_the_sylius_404(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $client->request('GET', '/en_US/nothing-here');

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getRequest()->attributes->has('_route'), 'No route matches, so Sylius answers its ordinary 404');
    }

    /**
     * @test
     */
    public function a_page_is_only_found_in_the_locale_of_its_slug(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $client->request('GET', '/de_DE/about-us');

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getRequest()->attributes->has('_route'));
    }

    /**
     * @test
     */
    public function sylius_routes_still_win_over_entry_urls(): void
    {
        $client = self::createClient();
        self::createPage('Login', 'login', 'Not the login page', published: true);

        $client->request('GET', '/en_US/login');

        self::assertRouteSame('sylius_shop_login', [], 'The entry route has priority -1000, so it is matched after every other route');
    }

    /**
     * @test
     */
    public function a_url_ending_in_a_slash_redirects_to_the_entry_url(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $client->request('GET', '/en_US/about-us/');

        // The route's path can't end in a slash, so Symfony's matcher redirects to the URL without it, as it does for every route
        self::assertResponseRedirects('http://localhost/en_US/about-us', 301);
    }

    /**
     * @test
     */
    public function it_answers_404_for_a_disabled_page(): void
    {
        $client = self::createClient();
        self::change(self::createPage('About us', 'about-us', 'Hello', published: true), static fn (PageInterface $page) => $page->setEnabled(false));

        $client->request('GET', '/en_US/about-us');

        self::assertResponseStatusCodeSame(404);
        self::assertRouteSame('setono_sylius_cms_shop_entry', [], 'The URL exists, so the route claims it, and the controller answers 404');
    }

    /**
     * Unpublishing means turning enabled off. See "What takes effect on save" in docs/spec/publishing.md
     *
     * @test
     */
    public function it_shows_the_published_content_a_page_kept_when_it_is_turned_back_on(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::change($page, static fn (PageInterface $page) => $page->setEnabled(false));
        self::change($page, static fn (PageInterface $page) => $page->setEnabled(true));

        $client->request('GET', '/en_US/about-us');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-heading', 'Hello');
    }

    /**
     * @test
     */
    public function it_answers_404_for_a_page_before_its_date_window_and_after_it(): void
    {
        $client = self::createClient();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        self::change(self::createPage('Soon', 'soon', 'Soon', published: true), static fn (PageInterface $page) => $page->setDateWindow(new DateWindow($now->modify('+1 hour'))));
        self::change(self::createPage('Ended', 'ended', 'Ended', published: true), static fn (PageInterface $page) => $page->setDateWindow(new DateWindow($now->modify('-2 hours'), $now->modify('-1 hour'))));
        self::change(self::createPage('Now', 'now', 'Now', published: true), static fn (PageInterface $page) => $page->setDateWindow(new DateWindow($now->modify('-1 hour'), $now->modify('+1 hour'))));

        $client->request('GET', '/en_US/soon');
        self::assertResponseStatusCodeSame(404);
        self::assertRouteSame('setono_sylius_cms_shop_entry');

        $client->request('GET', '/en_US/ended');
        self::assertResponseStatusCodeSame(404);
        self::assertRouteSame('setono_sylius_cms_shop_entry');

        $client->request('GET', '/en_US/now');
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-heading', 'Now');
    }

    /**
     * The URL doesn't exist in a channel the page isn't in, so the request falls through to Sylius's 404
     *
     * @test
     */
    public function it_answers_404_for_a_page_in_a_channel_it_is_not_in(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello', published: true, channels: ['OTHER']);

        $client->request('GET', '/en_US/about-us');

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getRequest()->attributes->has('_route'));
    }

    /**
     * A URL is unique per channel and locale. See "Channels" in docs/spec/localization.md
     *
     * @test
     */
    public function it_lets_pages_with_no_channel_in_common_share_a_url(): void
    {
        $client = self::createClient();
        self::createPage('Elsewhere', 'about-us', 'In the other channel', published: true, channels: ['OTHER']);
        self::createPage('About us', 'about-us', 'In this channel', published: true, channels: ['WEB']);

        $client->request('GET', '/en_US/about-us');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-heading', 'In this channel');
    }

    /**
     * See "An entry's audience" in docs/spec/targeting.md
     *
     * @test
     */
    public function it_answers_404_for_a_page_outside_its_audience_without_a_redirect_to_sign_in(): void
    {
        $client = self::createClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        self::createCustomerGroup('retail', 'Retail');
        $wholesaler = self::createShopUser('wholesaler@example.com', 'wholesale');
        $retailer = self::createShopUser('retailer@example.com', 'retail');
        $withoutGroup = self::createShopUser('shopper@example.com');
        self::change(self::createPage('Prices', 'prices', 'Wholesale prices', published: true), static fn (PageInterface $page) => $page->setAudience(Audience::only('wholesale')));

        $client->request('GET', '/en_US/prices');
        self::assertResponseStatusCodeSame(404, 'A guest is outside the audience, and gets no redirect to sign in');
        self::assertRouteSame('setono_sylius_cms_shop_entry');

        $client->loginUser($retailer, 'shop');
        $client->request('GET', '/en_US/prices');
        self::assertResponseStatusCodeSame(404);

        $client->loginUser($withoutGroup, 'shop');
        $client->request('GET', '/en_US/prices');
        self::assertResponseStatusCodeSame(404);

        $client->loginUser($wholesaler, 'shop');
        $client->request('GET', '/en_US/prices');
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-heading', 'Wholesale prices');
    }

    /**
     * @test
     */
    public function it_answers_404_for_a_page_for_everyone_except_a_group_to_that_group_only(): void
    {
        $client = self::createClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $wholesaler = self::createShopUser('wholesaler@example.com', 'wholesale');
        self::change(self::createPage('Offers', 'offers', 'Offers', published: true), static fn (PageInterface $page) => $page->setAudience(Audience::except('wholesale')));

        $client->request('GET', '/en_US/offers');
        self::assertResponseStatusCodeSame(200);

        $client->loginUser($wholesaler, 'shop');
        $client->request('GET', '/en_US/offers');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param callable(PageInterface): void $change
     */
    private static function change(PageInterface $page, callable $change): void
    {
        $change($page);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
    }
}
