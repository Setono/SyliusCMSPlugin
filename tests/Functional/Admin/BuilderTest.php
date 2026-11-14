<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BuilderTest extends WebTestCase
{
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
        self::assertSame('/admin/', $config['backUrl']);
        self::assertSame([
            'registry' => '/admin/cms/api/registry',
            'entry' => sprintf('/admin/cms/api/entries/%s', $uuid),
            'publish' => sprintf('/admin/cms/api/entries/%s/publish', $uuid),
        ], $config['endpoints']);
        self::assertSame([
            'setono_sylius_cms.builder.loading' => 'Loading…',
            'setono_sylius_cms.builder.load_failed' => 'Couldn\'t load the entry',
        ], $config['strings'], 'The strings the app shows before the registry has loaded');
    }

    /**
     * @test
     */
    public function its_back_button_returns_to_a_local_url_it_was_opened_from(): void
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
        self::assertSame('/admin/', $config['backUrl']);
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
