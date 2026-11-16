<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\PageInterface;

/**
 * The checks of an entry's URL on save, on publish and when its channels change. See "Unique per channel and locale"
 * and "Reserved URLs" in docs/spec/urls-and-seo.md
 */
final class UrlChecksTest extends BuilderApiTestCase
{
    /**
     * @test
     */
    public function it_refuses_a_publish_on_a_url_another_entry_has_published_in_a_channel_and_locale_they_share(): void
    {
        $client = self::createClient();
        self::createPage('About', 'about-us', 'The first', published: true, channels: ['WEB', 'MOBILE']);
        $page = self::createPage('About us', 'about-us', 'Hello', channels: ['WEB']);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', self::uri($page, '/publish'), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($response);
        self::assertSame([
            ['block' => null, 'field' => 'slug', 'locale' => 'en_US', 'message' => 'The URL /en_US/about-us is already the URL of "About" in Setono CMS Test Store.'],
        ], $response['errors'] ?? null);
        self::assertFalse(self::reloadPage($page)->isPublished());
    }

    /**
     * @test
     */
    public function it_allows_a_publish_when_the_entries_have_no_channel_in_common(): void
    {
        $client = self::createClient();
        self::createPage('Elsewhere', 'about-us', 'In the other channel', published: true, channels: ['OTHER']);
        $page = self::createPage('About us', 'about-us', 'Hello', channels: ['WEB']);
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'POST', self::uri($page, '/publish'), null, $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertTrue(self::reloadPage($page)->isPublished());
    }

    /**
     * @test
     */
    public function it_refuses_a_publish_on_a_url_a_sylius_route_takes(): void
    {
        $client = self::createClient();
        // Made without the builder, whose save would have refused the slug
        $page = self::createPage('Cart', 'cart', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', self::uri($page, '/publish'), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($response);
        self::assertSame([
            ['block' => null, 'field' => 'slug', 'locale' => 'en_US', 'message' => 'The URL /en_US/cart is taken by the route sylius_shop_cart_summary.'],
        ], $response['errors'] ?? null);
    }

    /**
     * @test
     */
    public function it_keeps_the_slug_whose_url_a_route_takes_on_save_names_the_route_and_saves_the_rest(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);
        $lockToken = self::lockToken($client, $page);

        $response = self::json($client, 'PATCH', self::uri($page), ['lockToken' => $lockToken, 'changes' => [
            ['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'en_US', 'value' => 'Your cart'],
            ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => 'cart'],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame($lockToken + 1, $response['lockToken'] ?? null);
        self::assertSame([
            ['block' => null, 'field' => 'slug', 'locale' => 'en_US', 'message' => 'The URL /en_US/cart is taken by the route sylius_shop_cart_summary.'],
        ], $response['refused'] ?? null);

        $saved = self::reloadPage($page);
        self::assertSame('Your cart', $saved->getTitle());
        self::assertSame('about-us', $saved->getSlug());
    }

    /**
     * @test
     */
    public function it_answers_a_save_without_refusals_when_it_refuses_nothing(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'PATCH', self::uri($page), ['lockToken' => self::lockToken($client, $page), 'changes' => [
            ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => 'about/team'],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertArrayNotHasKey('refused', $response);
        self::assertSame('about/team', self::reloadPage($page)->getSlug());
    }

    /**
     * @test
     */
    public function it_warns_the_builder_of_a_url_another_entry_has_drafted_and_refuses_one_it_has_published(): void
    {
        $client = self::createClient();
        self::createPage('Our team', 'team', 'Meet us');
        self::createPage('About', 'about', 'The first', published: true);
        $page = self::createPage('About us', 'about', 'Hello');
        $csrfToken = self::openBuilder($client, $page);
        self::json($client, 'PATCH', self::uri($page), ['lockToken' => self::lockToken($client, $page), 'changes' => [
            ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => 'team'],
        ]], $csrfToken);

        $response = self::json($client, 'POST', self::uri($page, '/validate'));

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame([], $response['errors'] ?? null);
        $response = self::json($client, 'GET', self::uri($page, '/urls'));
        self::assertIsArray($response);
        self::assertSame([
            ['block' => null, 'field' => 'slug', 'locale' => 'en_US', 'message' => '"Our team" has the URL /en_US/team in its draft too, in Setono CMS Test Store. Only one of them can be published there.'],
        ], $response['warnings'] ?? null);

        self::json($client, 'PATCH', self::uri($page), ['lockToken' => self::lockToken($client, $page), 'changes' => [
            ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => 'about'],
        ]], $csrfToken);
        $response = self::json($client, 'POST', self::uri($page, '/validate'));

        self::assertIsArray($response);
        self::assertSame([
            ['block' => null, 'field' => 'slug', 'locale' => 'en_US', 'message' => 'The URL /en_US/about is already the URL of "About" in Setono CMS Test Store.'],
        ], $response['errors'] ?? null);
        $response = self::json($client, 'GET', self::uri($page, '/urls'));
        self::assertIsArray($response);
        self::assertSame([], $response['warnings'] ?? null);
    }

    /**
     * @test
     */
    public function it_warns_the_builder_of_a_url_in_a_published_entrys_draft(): void
    {
        $client = self::createClient();
        $other = self::createPage('Sale', 'sale', 'Cheap', published: true);
        self::saveChanges($other, [
            ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => 'sale_50%'],
            ['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'en_US', 'value' => 'Half price'],
        ]);
        self::createPage('Almost', 'sale_500%', 'Almost the same slug', published: true);
        $page = self::createPage('Our sale', 'sale_50%', 'Hello');
        self::openBuilder($client, $page);

        $response = self::json($client, 'POST', self::uri($page, '/validate'));

        self::assertIsArray($response);
        self::assertSame([], $response['errors'] ?? null, 'The published URL of the other entry is another');
        $response = self::json($client, 'GET', self::uri($page, '/urls'));
        self::assertIsArray($response);
        self::assertSame([
            ['block' => null, 'field' => 'slug', 'locale' => 'en_US', 'message' => '"Half price" has the URL /en_US/sale_50% in its draft too, in Setono CMS Test Store. Only one of them can be published there.'],
        ], $response['warnings'] ?? null);
    }

    /**
     * @test
     */
    public function it_warns_the_builder_of_a_published_url_a_route_takes_now(): void
    {
        $client = self::createClient();
        $page = self::createPage('Log in', 'login', 'Hello', published: true);
        self::openBuilder($client, $page);

        $response = self::json($client, 'GET', self::uri($page, '/urls'));

        self::assertResponseIsSuccessful();
        self::assertSame(['warnings' => [
            ['block' => null, 'field' => 'slug', 'locale' => 'en_US', 'message' => 'Shoppers don\'t see this at /en_US/login: the route sylius_shop_login takes the URL.'],
        ], 'oldUrls' => []], $response);
    }

    /**
     * @test
     */
    public function it_gives_the_builder_the_urls_of_the_old_slugs_that_redirect(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about', 'Hello', published: true);
        self::saveChanges($page, [['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => 'about-us']]);
        self::publishEntry($page);
        self::openBuilder($client, $page);

        $response = self::json($client, 'GET', self::uri($page, '/urls'));

        self::assertResponseIsSuccessful();
        self::assertSame(['warnings' => [], 'oldUrls' => ['en_US' => ['/en_US/about']]], $response);
    }

    /**
     * @test
     */
    public function it_refuses_a_channel_change_on_save_that_would_make_a_published_url_clash(): void
    {
        $client = self::createClient();
        self::createChannel('MOBILE', 'Mobile');
        self::createPage('About', 'about-us', 'The first', published: true, channels: ['MOBILE']);
        $page = self::createPage('About us', 'about-us', 'Hello', published: true, channels: ['WEB']);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'PUT', self::uri($page, '/settings'), ['channels' => ['MOBILE', 'WEB']], $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($response);
        self::assertSame('The URL /en_US/about-us is already the URL of "About" in Mobile, so this can\'t be shown there too.', $response['error'] ?? null);
        self::assertSame(['WEB'], self::reloadPage($page)->getChannelCodes());
    }

    /**
     * @test
     */
    public function it_saves_a_channel_change_of_a_never_published_entry_and_checks_it_on_publish(): void
    {
        $client = self::createClient();
        self::createChannel('MOBILE', 'Mobile');
        self::createPage('About', 'about-us', 'The first', published: true, channels: ['MOBILE']);
        $page = self::createPage('About us', 'about-us', 'Hello', channels: ['WEB']);
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'PUT', self::uri($page, '/settings'), ['channels' => ['MOBILE', 'WEB']], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['MOBILE', 'WEB'], self::reloadPage($page)->getChannelCodes());
    }

    private static function uri(PageInterface $page, string $endpoint = ''): string
    {
        return '/admin/cms/api/entries/' . $page->getUuid() . $endpoint;
    }
}
