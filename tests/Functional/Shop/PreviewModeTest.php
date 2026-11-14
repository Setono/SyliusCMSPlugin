<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class PreviewModeTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function a_never_published_page_renders_its_working_copy_with_a_valid_token(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::token($page));

        self::assertResponseStatusCodeSame(200);
        self::assertRouteSame('setono_sylius_cms_shop_entry');
        self::assertSelectorTextSame('h1', 'About us');
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
        self::assertSelectorExists('#footer', 'Everything else on the page renders as it does for shoppers');
    }

    /**
     * @test
     */
    public function a_preview_is_neither_cached_nor_indexed(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::token($page));

        $response = $client->getResponse();
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertFalse($response->headers->hasCacheControlDirective('max-age'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
    }

    /**
     * @test
     */
    public function a_never_published_page_answers_404_without_a_token(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us');

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getResponse()->headers->has('X-Robots-Tag'));
        self::assertFalse($client->getResponse()->headers->hasCacheControlDirective('no-store'));
    }

    /**
     * @test
     */
    public function an_expired_token_is_ignored(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $signedTwoHoursAgo = new PreviewTokenSigner(self::secret(), new MockClock('-2 hours'));

        $client->request('GET', '/en_US/about-us?_cms_preview=' . $signedTwoHoursAgo->sign(new Preview($page->getUuid(), 'WEB', 'en_US')));

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getResponse()->headers->has('X-Robots-Tag'));
        self::assertFalse($client->getResponse()->headers->hasCacheControlDirective('no-store'));
    }

    /**
     * @test
     */
    public function a_tampered_token_is_ignored(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $signedWithAnotherSecret = new PreviewTokenSigner('not the secret', new MockClock());

        $client->request('GET', '/en_US/about-us?_cms_preview=' . $signedWithAnotherSecret->sign(new Preview($page->getUuid(), 'WEB', 'en_US')));

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getResponse()->headers->has('X-Robots-Tag'));
        self::assertFalse($client->getResponse()->headers->hasCacheControlDirective('no-store'));
    }

    /**
     * @test
     */
    public function other_entries_render_as_they_do_for_shoppers(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        self::createPage('Imprint', 'imprint', 'Who we are');

        $client->request('GET', '/en_US/imprint?_cms_preview=' . self::token($page));

        self::assertResponseStatusCodeSame(404, 'Only the edited entry ignores its publish state');
    }

    /**
     * @test
     */
    public function templates_can_tell_they_render_in_the_builders_preview(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::token($page));

        self::assertSame('builder', $client->getRequest()->attributes->get('_cms_preview_mode'));
        self::assertSelectorNotExists('#test-app-analytics');
        self::assertSame('builder', $client->getCrawler()->filter('meta[name="test-app-preview-mode"]')->attr('content'));
    }

    /**
     * @test
     */
    public function templates_see_no_preview_mode_for_shoppers(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $client->request('GET', '/en_US/about-us');

        self::assertResponseStatusCodeSame(200);
        self::assertNull($client->getRequest()->attributes->get('_cms_preview_mode'));
        self::assertSelectorExists('#test-app-analytics');
        self::assertSelectorNotExists('meta[name="test-app-preview-mode"]');
    }

    /**
     * @test
     */
    public function the_preview_brackets_each_blocks_output_with_markers_that_carry_its_key(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::token($page));

        self::assertMatchesRegularExpression(
            '#<!--cms:block 0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f heading--><h2 class="cms-heading">Hello</h2>\s*<!--/cms:block 0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f-->#',
            (string) $client->getResponse()->getContent(),
        );
    }

    /**
     * @test
     */
    public function the_preview_gets_the_bridge_script(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::token($page));

        self::assertSame('/bundles/setonosyliuscmsplugin/builder/bridge.js', $client->getCrawler()->filter('body > script[data-cms-bridge]')->attr('src'));
        self::assertSame('module', $client->getCrawler()->filter('script[data-cms-bridge]')->attr('type'));
    }

    /**
     * @test
     */
    public function a_shoppers_response_has_no_markers_and_no_bridge(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $client->request('GET', '/en_US/about-us');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
        self::assertStringNotContainsString('cms:block', (string) $client->getResponse()->getContent());
        self::assertSelectorNotExists('script[data-cms-bridge]');
    }

    /**
     * @test
     */
    public function the_token_forces_the_channel_whatever_the_host(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        self::createChannel('OTHER', 'Other Store', 'other.example');

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::token($page, 'OTHER'));

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('title', 'Other Store', 'The shop layout titles the page with the channel name');
    }

    private static function token(PageInterface $page, string $channel = 'WEB', string $locale = 'en_US'): string
    {
        /** @var PreviewTokenSigner $signer */
        $signer = self::getContainer()->get(PreviewTokenSigner::class);

        return $signer->sign(new Preview($page->getUuid(), $channel, $locale));
    }

    private static function secret(): string
    {
        $secret = self::getContainer()->getParameter('kernel.secret');
        self::assertIsString($secret);

        return $secret;
    }

    private static function createChannel(string $code, string $name, string $hostname): void
    {
        /** @var ExampleFactoryInterface $factory */
        $factory = self::getContainer()->get('sylius.fixture.example_factory.channel');
        $channel = $factory->create([
            'code' => $code,
            'name' => $name,
            'hostname' => $hostname,
            'enabled' => true,
            'locales' => ['en_US'],
            'default_locale' => 'en_US',
            'currencies' => ['USD'],
            'base_currency' => 'USD',
            'menu_taxon' => null,
            'default_tax_zone' => null,
        ]);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.channel');
        $manager->persist($channel);
        $manager->flush();
    }
}
