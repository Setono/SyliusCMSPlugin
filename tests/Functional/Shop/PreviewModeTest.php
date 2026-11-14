<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
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
    public function a_published_page_renders_its_draft_with_a_valid_token_while_shoppers_see_the_rows(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);

        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($page);
        $block = $workingCopy->getBlockTree()->blocks[0] ?? null;
        self::assertNotNull($block);
        $block->setValue('text', 'en_US', 'Hello world');
        $workingCopy->setBlockTree(new StoredTree($workingCopy->getBlockTree()->layout, [$block]));
        $workingCopies->save($page, $workingCopy);

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::token($page));
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello world');

        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
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
     * See "Preview mode" in docs/spec/builder.md
     *
     * @test
     */
    public function the_edited_entry_ignores_its_enabled_switch_date_window_and_audience(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $page->setEnabled(false);
        $page->setDateWindow(new DateWindow(null, new \DateTimeImmutable('2000-01-01T00:00:00Z')));
        $page->setAudience(Audience::only('wholesale'));
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        $client->request('GET', '/en_US/about-us');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::token($page));
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
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
