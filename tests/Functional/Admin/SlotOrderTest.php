<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SlotsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The placements of a capped slot on a snippet's overview page: each one's position and who it wins for, and the slot's
 * Order… dialog, which lists every snippet's placements in the slot to put in order. SlotsKernel caps shop.top and
 * store_homepage at 1. See "Placements" in docs/spec/admin.md and "Capped slots" in docs/spec/slots.md
 */
final class SlotOrderTest extends WebTestCase
{
    use Customers;
    use Entries;
    use Placements;

    /**
     * @test
     */
    public function it_shows_each_placement_s_position_with_its_slot_s_cap_and_who_it_wins_for(): void
    {
        $client = self::signedInClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $wholesale = self::createSnippet('Wholesale prices');
        self::channelsAndAudience()->setAudience($wholesale, Audience::only('wholesale', '@guest'));
        self::entityManager()->flush();
        $welcome = self::createSnippet('Welcome');
        $sale = self::createSnippet('Winter sale');
        self::place($wholesale, 'shop.top');
        self::place($welcome, 'shop.top');
        self::place($sale, 'shop.top');
        self::place($sale, 'shop.footer');

        self::assertSame([['1 of 3 · capped at 1', 'Only Guests, Wholesale']], self::positionsAndWinsFor($client->request('GET', self::overview($wholesale))));
        self::assertSame([['2 of 3 · capped at 1', 'Everyone, where no placement above it shows']], self::positionsAndWinsFor($client->request('GET', self::overview($welcome))));
        self::assertSame([
            ['3 of 3 · capped at 1', 'No one: a placement above it shows on every page to everyone'],
            ['1 of 1', 'Everyone'],
        ], self::positionsAndWinsFor($client->request('GET', self::overview($sale))));
    }

    /**
     * @test
     */
    public function it_lists_every_snippet_s_placements_in_a_capped_slot_in_its_order_dialog_and_saves_the_order_it_is_given(): void
    {
        $client = self::signedInClient();
        $freeShipping = self::createSnippet('Free shipping', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Free shipping')], published: true);
        $sale = self::createSnippet('Winter sale', [self::heading('0193a5be-0000-7000-8000-000000000002', 'Winter sale')], published: true);
        $first = self::place($freeShipping, 'shop.top');
        $second = self::place($sale, 'shop.top');
        self::place($sale, 'shop.footer');
        self::assertSame(['Free shipping'], self::headingsIn($client->request('GET', '/en_US/'), '.cms-slot--shop-top'));

        $crawler = $client->request('GET', self::overview($sale));

        // Only the capped slot is ordered in a dialog, and its row opens it
        self::assertCount(0, $crawler->filter('[data-placement][data-slot="shop.top"] form[data-move]'));
        $opener = $crawler->filter('[data-placement][data-slot="shop.top"] [data-cms-slot-order-open]');
        self::assertSame('Order…', trim($opener->text()));
        $dialog = $crawler->filter('#' . $opener->attr('aria-controls'));
        self::assertSame('shop.top', $dialog->attr('data-slot'));
        self::assertSame('Order of "Every page: the top of the header"', $dialog->filter('h2')->text());
        self::assertSame([
            [$first->getUuid(), 'Free shipping', null],
            [$second->getUuid(), 'Winter sale', 'This snippet'],
        ], self::orderedPlacements($dialog));
        self::assertCount(0, $dialog->filter('[data-slot-warning]'));
        self::assertCount(1, $crawler->filter('[data-placement][data-slot="shop.footer"] form[data-move]'));

        $form = $dialog->filter('form')->form();
        $form->setValues(['placements' => [$second->getUuid(), $first->getUuid()]]);
        $client->submit($form);

        self::assertResponseRedirects(self::overview($sale));
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.positive', 'The order has been saved. The slot shows its placements in the new order at once.');
        self::assertSame([[$second->getUuid(), 'Winter sale', 'This snippet'], [$first->getUuid(), 'Free shipping', null]], self::orderedPlacements($crawler->filter('dialog[data-slot="shop.top"]')));
        self::assertSame([['shop.footer', 0], ['shop.top', 0]], self::placementsOf($sale));
        self::assertSame([['shop.top', 1]], self::placementsOf($freeShipping));

        self::assertSame(['Winter sale'], self::headingsIn($client->request('GET', '/en_US/'), '.cms-slot--shop-top'));
    }

    /**
     * @test
     */
    public function it_shows_the_slot_s_warnings_in_its_order_dialog(): void
    {
        $client = self::signedInClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $snippet = self::createSnippet('Wholesale prices');
        self::channelsAndAudience()->setAudience($snippet, Audience::only('wholesale'));
        self::entityManager()->flush();
        self::place($snippet, 'store_homepage');
        self::setHomepageOf(self::createPage('Home', 'home', 'Welcome'), 'WEB');

        $dialog = $client->request('GET', self::overview($snippet))->filter('dialog[data-slot="store_homepage"]');

        self::assertSame([
            'no_catch_all' => 'None of its placements is a catch-all, with no page scope and a snippet for everyone, so some pages or shoppers may see nothing there.',
            'hidden_by_homepage' => 'It doesn\'t show in Setono CMS Test Store, whose homepage is a CMS page, as Sylius\'s own homepage doesn\'t render there.',
        ], self::warnings($dialog));
    }

    /**
     * Such as one made in a dialog opened before another tab added a placement to the slot
     *
     * @test
     */
    public function it_refuses_an_order_that_is_not_the_slot_s_placements_and_keeps_the_order_as_it_was(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Free shipping');
        $first = self::place($snippet, 'shop.top');
        $crawler = $client->request('GET', self::overview($snippet));
        $second = self::place(self::createSnippet('Winter sale'), 'shop.top');

        $client->submit($crawler->filter('dialog[data-slot="shop.top"] form')->form());

        self::assertResponseRedirects(self::overview($snippet));
        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'The slot\'s placements changed meanwhile, so the order wasn\'t saved. Order them again.');
        self::assertSame([0, 1], [$first->getPosition(), $second->getPosition()]);
    }

    /**
     * @test
     */
    public function it_answers_404_for_the_order_of_a_slot_the_snippet_is_not_placed_in(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Free shipping');
        $others = self::place(self::createSnippet('Winter sale'), 'shop.top');
        $crawler = $client->request('GET', self::overview($snippet));

        $client->request('POST', sprintf('%s/placements/order', self::overview($snippet)), [
            '_csrf_token' => $crawler->filter('[data-section="placements"] input[name="_csrf_token"]')->attr('value'),
            'slot' => 'shop.top',
            'placements' => [$others->getUuid()],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    protected static function getKernelClass(): string
    {
        return SlotsKernel::class;
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    private static function overview(SnippetInterface $snippet): string
    {
        return sprintf('/admin/cms/entry/%s', $snippet->getUuid());
    }

    /**
     * Each of the snippet's placements, as [its position, who it wins for]
     *
     * @return array<mixed>
     */
    private static function positionsAndWinsFor(Crawler $crawler): array
    {
        return $crawler->filter('[data-section="placements"] [data-placement]')->each(static fn (Crawler $row): array => [
            $row->filter('[data-position]')->text(),
            $row->filter('[data-wins-for]')->text(),
        ]);
    }

    /**
     * The placements an Order… dialog lists, in order, as [UUID, its snippet's title, "This snippet" or null]
     *
     * @return array<mixed>
     */
    private static function orderedPlacements(Crawler $dialog): array
    {
        return $dialog->filter('[data-ordered-placement]')->each(static fn (Crawler $item): array => [
            $item->attr('data-ordered-placement'),
            $item->filter('[data-snippet-title]')->text(),
            $item->filter('[data-this-snippet]')->count() > 0 ? $item->filter('[data-this-snippet]')->text() : null,
        ]);
    }

    /**
     * @return array<mixed>
     */
    private static function warnings(Crawler $dialog): array
    {
        $warnings = [];
        $dialog->filter('[data-slot-warning]')->each(static function (Crawler $warning) use (&$warnings): void {
            $warnings[(string) $warning->attr('data-slot-warning')] = $warning->text();
        });

        return $warnings;
    }

    /**
     * @return array<mixed>
     */
    private static function headingsIn(Crawler $crawler, string $slot): array
    {
        return $crawler->filter($slot . ' .cms-heading')->each(static fn (Crawler $heading): string => $heading->text());
    }
}
