<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Html;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tests\Support\ProfiledQueries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Snippets placed in the slots of the shop's pages. See "Slots", "Placements" and "Rendering a slot" in
 * docs/spec/slots.md and docs/spec/rendering.md
 */
final class SlotsTest extends WebTestCase
{
    use Entries;
    use Placements;
    use ProfiledQueries;

    private const FREE_SHIPPING = '0193a5be-0000-7000-8000-000000000001';

    private const OPENING_HOURS = '0193a5be-0000-7000-8000-000000000002';

    /**
     * @test
     */
    public function it_shows_a_published_snippet_placed_in_the_footer_on_every_page_inside_the_slot_s_wrapper(): void
    {
        $client = self::createClient();
        self::place(self::createSnippet('Free shipping', [self::heading(self::FREE_SHIPPING, 'Free shipping over $50')], published: true), 'shop.footer');
        self::createPage('About us', 'about-us', 'About us', published: true);

        foreach (['/en_US/', '/en_US/about-us', '/en_US/cart/', '/en_US/login'] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertResponseIsSuccessful($url);
            self::assertSame(
                '<div class="cms-slot cms-slot--shop-footer"><h2 class="cms-heading">Free shipping over $50</h2></div>',
                Html::withoutWhitespaceBetweenTags($crawler->filter('.cms-slot')->outerHtml()),
                $url,
            );
        }
    }

    /**
     * @test
     */
    public function it_shows_the_snippets_in_a_slot_in_the_order_editors_set(): void
    {
        $client = self::createClient();
        $freeShipping = self::place(self::createSnippet('Free shipping', [self::heading(self::FREE_SHIPPING, 'Free shipping')], published: true), 'shop.footer');
        self::place(self::createSnippet('Opening hours', [self::heading(self::OPENING_HOURS, 'Opening hours')], published: true), 'shop.footer');

        self::assertSame(['Free shipping', 'Opening hours'], self::headingsIn($client->request('GET', '/en_US/'), 'shop-footer'));

        self::placementEditor()->moveDown($freeShipping);

        self::assertSame(['Opening hours', 'Free shipping'], self::headingsIn($client->request('GET', '/en_US/'), 'shop-footer'));
    }

    /**
     * @test
     */
    public function it_shows_nothing_of_a_snippet_that_is_not_published_disabled_or_not_in_the_channel(): void
    {
        $client = self::createClient();
        self::place(self::createSnippet('Never published', [self::heading(self::FREE_SHIPPING, 'Never published')]), 'shop.footer');
        $disabled = self::createSnippet('Disabled', [self::heading(self::OPENING_HOURS, 'Disabled')], published: true);
        $disabled->setEnabled(false);
        self::entityManager()->flush();
        self::place($disabled, 'shop.footer');
        self::place(self::createSnippet('Elsewhere', [self::heading('0193a5be-0000-7000-8000-000000000003', 'Elsewhere')], published: true, channels: []), 'shop.footer');

        $crawler = $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.cms-slot'), 'A slot with nothing to show prints no wrapper');
    }

    /**
     * @test
     */
    public function it_prints_nothing_for_an_empty_slot_not_even_its_wrapper(): void
    {
        $client = self::createClient();
        self::place(self::createSnippet('Footer', [self::heading(self::FREE_SHIPPING, 'Footer')], published: true), 'shop.footer');
        // A published snippet whose only block is hidden shows nothing
        $hidden = self::heading(self::OPENING_HOURS, 'Hidden');
        $hidden->conditions = [['kind' => 'locale', 'negate' => false, 'params' => ['locales' => ['de_DE']]]];
        self::place(self::createSnippet('Hidden', [$hidden], published: true), 'shop.top');

        $crawler = $client->request('GET', '/en_US/');

        self::assertSame(['cms-slot cms-slot--shop-footer'], $crawler->filter('.cms-slot')->each(static fn (Crawler $slot): string => (string) $slot->attr('class')));
    }

    /**
     * @test
     */
    public function it_reads_the_channel_s_placements_with_one_query_for_every_slot_on_the_page(): void
    {
        $client = self::createClient();
        self::place(self::createSnippet('Top', [self::heading(self::FREE_SHIPPING, 'Top')], published: true), 'shop.top');
        self::place(self::createSnippet('Footer', [self::heading(self::OPENING_HOURS, 'Footer')], published: true), 'shop.footer');
        $client->request('GET', '/en_US/');

        $client->enableProfiler();
        $crawler = $client->request('GET', '/en_US/');

        self::assertCount(2, $crawler->filter('.cms-slot'));
        self::assertCount(1, self::queriesOf($client, 'setono_sylius_cms__placement'));
    }

    /**
     * Only the edited entry is marked for the builder to select and type in. See "Preview mode and share links" in
     * docs/spec/rendering.md
     *
     * @test
     */
    public function it_shows_the_snippets_in_the_slots_of_the_builder_s_preview_as_shoppers_see_them(): void
    {
        $client = self::createClient();
        self::place(self::createSnippet('Free shipping', [self::heading(self::FREE_SHIPPING, 'Free shipping')], published: true), 'shop.footer');
        $page = self::createPage('About us', 'about-us', 'About us');

        $crawler = $client->request('GET', '/en_US/about-us?_cms_preview=' . self::previewToken($page));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<!--cms:block', $crawler->filter('.cms-tree')->html());
        self::assertSame('<h2 class="cms-heading">Free shipping</h2>', Html::withoutWhitespaceBetweenTags($crawler->filter('.cms-slot--shop-footer')->html()));
    }

    /**
     * @test
     */
    public function it_keeps_a_placement_in_a_slot_the_code_no_longer_declares_and_shows_nothing_of_it(): void
    {
        $client = self::createClient();
        $snippet = self::createSnippet('Gone', [self::heading(self::FREE_SHIPPING, 'Gone')], published: true);
        self::placeInUndeclaredSlot($snippet, 'shop.removed');

        $crawler = $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.cms-slot'));
        self::assertSame([['shop.removed', 0]], self::placementsOf($snippet));
    }

    /**
     * The texts of the headings in the slot with this modifier
     *
     * @return list<string>
     */
    private static function headingsIn(Crawler $crawler, string $modifier): array
    {
        return $crawler->filter(sprintf('.cms-slot--%s .cms-heading', $modifier))->each(static fn (Crawler $heading): string => $heading->text());
    }
}
