<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class PreviewModeTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function it_renders_the_working_copy_of_a_never_published_page_with_a_valid_token(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::previewToken($page));

        self::assertResponseStatusCodeSame(200);
        self::assertRouteSame('setono_sylius_cms_shop_entry');
        self::assertSelectorTextSame('h1', 'About us');
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
        self::assertSelectorExists('#footer', 'Everything else on the page renders as it does for shoppers');
    }

    /**
     * @test
     */
    public function it_renders_the_draft_of_a_published_page_with_a_valid_token_while_shoppers_see_the_rows(): void
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

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::previewToken($page));
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello world');

        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
    }

    /**
     * @test
     */
    public function it_keeps_a_preview_out_of_caches_and_search_engines_and_sends_no_referrer(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::previewToken($page));

        $response = $client->getResponse();
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertFalse($response->headers->hasCacheControlDirective('max-age'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        // The token in the URL stays out of the Referer header of what the page loads
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
    }

    /**
     * @test
     */
    public function it_answers_404_for_a_never_published_page_without_a_token(): void
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
    public function it_ignores_an_expired_token(): void
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
    public function it_ignores_a_tampered_token(): void
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
    public function it_ignores_the_edited_entrys_enabled_switch_date_window_and_audience(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $page->setEnabled(false);
        $page->setDateWindow(new DateWindow(null, new \DateTimeImmutable('2000-01-01T00:00:00Z')));
        self::channelsAndAudience()->setAudience($page, Audience::only('wholesale'));
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        $client->request('GET', '/en_US/about-us');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::previewToken($page));
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
    }

    /**
     * @test
     */
    public function it_renders_other_entries_as_they_do_for_shoppers(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        self::createPage('Imprint', 'imprint', 'Who we are');

        $client->request('GET', '/en_US/imprint?_cms_preview=' . self::previewToken($page));

        self::assertResponseStatusCodeSame(404, 'Only the edited entry ignores its publish state');
    }

    /**
     * @test
     */
    public function it_tells_templates_they_render_in_the_builders_preview(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::previewToken($page));

        self::assertSame('builder', $client->getRequest()->attributes->get('_cms_preview_mode'));
        self::assertSelectorNotExists('#test-app-analytics');
        self::assertSame('builder', $client->getCrawler()->filter('meta[name="test-app-preview-mode"]')->attr('content'));
    }

    /**
     * @test
     */
    public function it_shows_templates_no_preview_mode_for_shoppers(): void
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
    public function it_brackets_each_blocks_output_in_the_preview_with_markers_that_carry_its_key(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::previewToken($page));

        self::assertMatchesRegularExpression(
            '#<!--cms:block 0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f heading--><h2 class="cms-heading"><!--cms:field 0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f text text-->Hello<!--/cms:field 0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f text--></h2>\s*<!--/cms:block 0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f-->#',
            (string) $client->getResponse()->getContent(),
        );
    }

    /**
     * @test
     */
    public function it_gives_the_preview_the_bridge_script(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::previewToken($page));

        self::assertSame('/bundles/setonosyliuscmsplugin/builder/bridge.js', $client->getCrawler()->filter('body > script[data-cms-bridge]')->attr('src'));
        self::assertSame('module', $client->getCrawler()->filter('script[data-cms-bridge]')->attr('type'));
    }

    /**
     * @test
     */
    public function it_gives_a_shoppers_response_no_markers_and_no_bridge(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $client->request('GET', '/en_US/about-us');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
        self::assertStringNotContainsString('cms:block', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('cms:field', (string) $client->getResponse()->getContent());
        self::assertSelectorNotExists('script[data-cms-bridge]');
    }

    /**
     * @test
     */
    public function it_forces_the_tokens_channel_whatever_the_host(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        self::createChannel('OTHER', 'Other Store', 'other.example');
        self::addLocales('OTHER', 'da_DK');
        self::addTranslation($page, 'da_DK', 'Om os', 'om-os');

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::previewToken($page, 'OTHER'));

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('link[rel="alternate"][hreflang="da-DK"]', 'The hreflang links are those of the locales of the token\'s channel, which only OTHER has Danish among');
    }

    private static function secret(): string
    {
        $secret = self::getContainer()->getParameter('kernel.secret');
        self::assertIsString($secret);

        return $secret;
    }
}
