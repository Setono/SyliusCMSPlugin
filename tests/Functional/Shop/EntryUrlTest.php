<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EntryUrlTest extends WebTestCase
{
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
}
