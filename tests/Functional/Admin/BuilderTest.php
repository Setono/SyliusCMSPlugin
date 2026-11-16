<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BuilderTest extends WebTestCase
{
    use Customers;
    use Entries;

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');

        $client->request('GET', sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_loads_the_builder_app_with_its_config_in_its_own_full_screen_page(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $page = self::createPage('About us', 'about-us');
        $uuid = $page->getUuid();

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s/builder', $uuid));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('link[rel="stylesheet"][href="/bundles/setonosyliuscmsplugin/builder/builder.css"]');
        self::assertSelectorExists('script[type="module"][src="/bundles/setonosyliuscmsplugin/builder/builder.js"]');
        self::assertSelectorNotExists('#sidebar', 'The builder has its own layout, not the Sylius admin layout');

        $config = json_decode((string) $crawler->filter('#cms-builder')->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($config);
        self::assertSame($uuid, $config['entryUuid']);
        self::assertSame('en_US', $config['locale']);
        self::assertIsString($config['csrfToken']);
        self::assertNotSame('', $config['csrfToken']);
        // Opened from nowhere else, it goes back to the entry's overview page, which its More tab links to too
        self::assertSame(sprintf('/admin/cms/entry/%s', $uuid), $config['backUrl']);
        self::assertSame(sprintf('/admin/cms/entry/%s', $uuid), $config['overviewUrl']);
        self::assertSame([
            'registry' => '/admin/cms/api/registry',
            'entry' => sprintf('/admin/cms/api/entries/%s', $uuid),
            'validate' => sprintf('/admin/cms/api/entries/%s/validate', $uuid),
            'publish' => sprintf('/admin/cms/api/entries/%s/publish', $uuid),
            'discard' => sprintf('/admin/cms/api/entries/%s/discard', $uuid),
            'settings' => sprintf('/admin/cms/api/entries/%s/settings', $uuid),
            'previewToken' => '/admin/cms/api/preview/token',
            'previewRender' => '/admin/cms/api/preview/render',
            'presence' => sprintf('/admin/cms/api/entries/%s/presence', $uuid),
        ], $config['endpoints']);
        self::assertSame([['code' => 'WEB', 'name' => 'Setono CMS Test Store', 'locales' => ['en_US']]], $config['channels'], "The store's channels, for the settings' channel picker and the preview's");
        self::assertSame([['code' => 'en_US', 'name' => 'English (United States)', 'fallbackChain' => ['en_US']]], $config['locales']);
        self::assertSame([], $config['customerGroups']);
        self::assertSame([
            'setono_sylius_cms.builder.loading' => 'Loading…',
            'setono_sylius_cms.builder.load_failed' => 'Couldn\'t load the entry',
        ], $config['strings'], 'The strings the app shows before the registry has loaded');
    }

    /**
     * The locale switcher offers the locales of the entry's channels, and an empty translatable field shows its
     * fallback value. The test app configures de_CH to fall back to de_DE. See "In the builder" in
     * docs/spec/localization.md
     *
     * @test
     */
    public function it_gives_the_builder_the_locales_of_each_channel_with_their_names_and_fallback_chains(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::addLocales('WEB', 'de_CH', 'da_DK');
        self::createChannel('OTHER', 'Other Store');
        $page = self::createPage('About us', 'about-us');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));

        $config = json_decode((string) $crawler->filter('#cms-builder')->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($config);
        self::assertSame([
            ['code' => 'WEB', 'name' => 'Setono CMS Test Store', 'locales' => ['en_US', 'da_DK', 'de_CH']],
            ['code' => 'OTHER', 'name' => 'Other Store', 'locales' => ['en_US']],
        ], $config['channels'], "A channel's default locale first, then the others by code");
        self::assertSame([
            ['code' => 'en_US', 'name' => 'English (United States)', 'fallbackChain' => ['en_US']],
            ['code' => 'da_DK', 'name' => 'Danish (Denmark)', 'fallbackChain' => ['da_DK', 'en_US']],
            ['code' => 'de_CH', 'name' => 'German (Switzerland)', 'fallbackChain' => ['de_CH', 'de_DE', 'en_US']],
        ], $config['locales']);
    }

    /**
     * The audience picker offers them
     *
     * @test
     */
    public function it_gives_the_builder_the_stores_customer_groups(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::createCustomerGroup('wholesale', 'Wholesale');
        self::createCustomerGroup('retail', 'Retail');
        $page = self::createPage('About us', 'about-us');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));

        $config = json_decode((string) $crawler->filter('#cms-builder')->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($config);
        self::assertSame([['code' => 'retail', 'name' => 'Retail'], ['code' => 'wholesale', 'name' => 'Wholesale']], $config['customerGroups']);
    }

    /**
     * @test
     */
    public function its_back_button_returns_to_a_local_url_it_was_opened_from_or_else_to_the_overview_page(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $page = self::createPage('About us', 'about-us');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s/builder?back=%s', $page->getUuid(), urlencode('/admin/products/')));
        $config = json_decode((string) $crawler->filter('#cms-builder')->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($config);
        self::assertSame('/admin/products/', $config['backUrl']);

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s/builder?back=%s', $page->getUuid(), urlencode('//evil.example/')));
        $config = json_decode((string) $crawler->filter('#cms-builder')->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($config);
        self::assertSame(sprintf('/admin/cms/entry/%s', $page->getUuid()), $config['backUrl']);
    }

    /**
     * @test
     */
    public function it_answers_404_for_an_entry_it_does_not_have(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $client->request('GET', '/admin/cms/entry/0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e/builder');

        self::assertResponseStatusCodeSame(404);
    }
}
