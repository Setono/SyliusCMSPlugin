<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Preview\PreviewModeListener;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\ShareLinks;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A share link's token alone decides what the shop shows at its entry's URL: the entry's draft, or its rows when it has
 * none, with the channel, locale, Shown to and Date it fixed, read-only and kept out of caches, search engines and
 * Referer headers. See "Share links" in docs/spec/publishing.md and "Preview mode and share links" in docs/spec/rendering.md
 */
final class ShareLinkTest extends WebTestCase
{
    use Customers;
    use Entries;
    use ShareLinks;

    /**
     * @test
     */
    public function it_renders_the_draft_of_a_never_published_page_and_keeps_it_out_of_caches_search_engines_and_referer_headers(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $shareLink = self::createShareLink($page);

        $client->request('GET', '/en_US/about-us?_cms_share=' . $shareLink->getToken());

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
        $response = $client->getResponse();
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertFalse($response->headers->hasCacheControlDirective('max-age'));
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertSame('share', $client->getRequest()->attributes->get(PreviewModeListener::REQUEST_ATTRIBUTE));
    }

    /**
     * @test
     */
    public function it_has_no_bridge_and_no_markers(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us?_cms_share=' . self::createShareLink($page)->getToken());

        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Hello', $content);
        self::assertStringNotContainsString('data-cms-bridge', $content);
        self::assertStringNotContainsString('<!--cms:block', $content);
        self::assertStringNotContainsString('<!--cms:field', $content);
    }

    /**
     * @test
     */
    public function it_renders_the_draft_of_a_published_page_while_shoppers_see_the_rows(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $shareLink = self::createShareLink($page);
        self::changeHeading($page, 'Hello world');

        $client->request('GET', '/en_US/about-us?_cms_share=' . $shareLink->getToken());
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello world');

        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');
    }

    /**
     * The link follows the draft: the draft it shows is the one there is when it's opened, at the draft's URL
     *
     * @test
     */
    public function it_shows_the_draft_as_it_is_when_the_link_is_opened_at_the_drafts_url(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $shareLink = self::createShareLink($page);
        self::changeHeading($page, 'Hello world', 'about');

        $client->request('GET', '/en_US/about?_cms_share=' . $shareLink->getToken());
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello world');

        // Someone who got the link before the slug changed still sees the draft at the published URL
        $client->request('GET', '/en_US/about-us?_cms_share=' . $shareLink->getToken());
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello world');
    }

    /**
     * @test
     */
    public function it_answers_a_revoked_token_as_for_a_shopper(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $shareLink = self::createShareLink($page);
        $token = $shareLink->getToken();
        self::revokeShareLink($shareLink);

        $client->request('GET', '/en_US/about-us?_cms_share=' . $token);

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getResponse()->headers->has('X-Robots-Tag'));
        self::assertFalse($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertFalse($client->getRequest()->attributes->has(PreviewModeListener::REQUEST_ATTRIBUTE));
    }

    /**
     * @test
     */
    public function it_answers_a_token_no_share_link_has_as_for_a_shopper(): void
    {
        $client = self::createClient();
        self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', '/en_US/about-us?_cms_share=k3Rq0Zl8vN2mX5pT7wY1aB4cD6eF9gH0iJ2kL5mN8oP');

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getResponse()->headers->has('X-Robots-Tag'));
    }

    /**
     * @test
     */
    public function it_does_not_apply_one_entrys_token_to_another_entrys_url(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        self::createPage('Imprint', 'imprint', 'Who we are');
        $shareLink = self::createShareLink($page);

        $client->request('GET', '/en_US/imprint?_cms_share=' . $shareLink->getToken());

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_does_not_apply_a_token_in_another_locale(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello');
        $shareLink = self::createShareLink($page);

        $client->request('GET', '/da_DK/about-us?_cms_share=' . $shareLink->getToken());

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Shown to is fixed when the link is made, at the preview's starting value: here the first group the entry's
     * audience allows. Whoever opens the link sees the page as that shopper
     *
     * @test
     */
    public function it_applies_the_shown_to_fixed_when_the_link_was_made(): void
    {
        $client = self::createClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $page = self::createPageWithBlocks('Prices', 'prices', [
            self::heading('0193a5be-0000-7000-8000-000000000001', 'Everyone'),
            self::headingIf('0193a5be-0000-7000-8000-000000000002', 'Wholesale prices', 'wholesale'),
            self::headingIf('0193a5be-0000-7000-8000-000000000003', 'Sign in for prices', '@guest'),
        ]);
        self::channelsAndAudience()->setAudience($page, Audience::only('wholesale'));
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        $shareLink = self::createShareLink($page);
        self::assertSame('wholesale', $shareLink->getShownTo());

        self::assertSame(['Everyone', 'Wholesale prices'], self::headings($client, '/en_US/prices?_cms_share=' . $shareLink->getToken()));
    }

    /**
     * Date is fixed when the link is made, at the preview's starting value: here the start of the entry's date window,
     * which is in the future. The entry itself shows whatever its date window
     *
     * @test
     */
    public function it_applies_the_date_fixed_when_the_link_was_made(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Christmas', 'christmas', [
            self::heading('0193a5be-0000-7000-8000-000000000001', 'Everyone'),
            self::headingWhen('0193a5be-0000-7000-8000-000000000002', 'At Christmas', ['from' => '2099-12-01T00:00:00Z']),
            self::headingWhen('0193a5be-0000-7000-8000-000000000003', 'Until Christmas', ['until' => '2099-12-01T00:00:00Z']),
        ]);
        $page->setDateWindow(new DateWindow(new \DateTimeImmutable('2099-12-01T00:00:00Z'), null));
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        $shareLink = self::createShareLink($page);
        self::assertSame('2099-12-01T00:00:00+00:00', $shareLink->getDate()?->format(\DATE_ATOM));

        self::assertSame(['Everyone', 'At Christmas'], self::headings($client, '/en_US/christmas?_cms_share=' . $shareLink->getToken()));
    }

    /**
     * The token is read from the main request, so its query parameter has to be on the page's URL
     *
     * @test
     */
    public function it_ignores_a_token_outside_the_query_string(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('POST', '/en_US/about-us', ['_cms_share' => self::createShareLink($page)->getToken()]);

        self::assertFalse($client->getResponse()->headers->has('X-Robots-Tag'));
    }

    /**
     * @return list<string>
     */
    private static function headings(KernelBrowser $client, string $url): array
    {
        $crawler = $client->request('GET', $url);
        self::assertResponseIsSuccessful();

        $headings = [];
        foreach ($crawler->filter('.cms-tree .cms-heading') as $heading) {
            $headings[] = $heading->textContent;
        }

        return $headings;
    }

    private static function headingIf(string $key, string $text, string $group): StoredBlock
    {
        $heading = self::heading($key, $text);
        $heading->conditions = [['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => [$group]]]];

        return $heading;
    }

    /**
     * @param array{from?: string, until?: string} $window
     */
    private static function headingWhen(string $key, string $text, array $window): StoredBlock
    {
        $heading = self::heading($key, $text);
        $heading->conditions = [['kind' => 'date_window', 'negate' => false, 'params' => $window]];

        return $heading;
    }

    /**
     * Saves a change to the page's working copy: its first heading's text and, when given, its English slug
     */
    private static function changeHeading(PageInterface $page, string $text, ?string $slug = null): void
    {
        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($page);
        $block = $workingCopy->getBlockTree()->blocks[0] ?? null;
        self::assertNotNull($block);
        $block->setValue('text', 'en_US', $text);
        $workingCopy->setBlockTree(new StoredTree($workingCopy->getBlockTree()->layout, [$block]));
        if (null !== $slug) {
            $workingCopy->setCurrentLocale('en_US');
            $workingCopy->setSlug($slug);
        }
        $workingCopies->save($page, $workingCopy);
    }
}
