<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\DBAL\Connection;
use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The Placements section of a snippet's overview page, where its placements are added, reordered and removed. They
 * take effect on save (ADR 0005). See "Placements" in docs/spec/admin.md and docs/spec/slots.md
 */
final class EntryPlacementsTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use Entries;
    use Placements;

    /**
     * @test
     */
    public function it_lists_a_snippet_s_placements_by_slot_with_each_one_s_position_among_the_slot_s_placements_of_every_snippet(): void
    {
        $client = self::signedInClient();
        $other = self::createSnippet('Opening hours');
        $snippet = self::createSnippet('Free shipping');
        self::place($other, 'shop.footer');
        self::place($snippet, 'shop.footer');
        self::place($snippet, 'shop.top');

        $crawler = $client->request('GET', self::overview($snippet));

        self::assertResponseIsSuccessful();
        self::assertSame('Placements', $crawler->filter('[data-section="placements"] h2')->text());
        self::assertSame([
            ['shop.top', 'Every page: the top of the header', '1 of 1'],
            ['shop.footer', 'Every page: the footer', '2 of 2'],
        ], self::rows($crawler));
    }

    /**
     * @test
     */
    public function it_shows_no_placements_section_for_an_entry_that_is_not_a_snippet(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-section="placements"]'));
    }

    /**
     * @test
     */
    public function it_offers_every_slot_to_a_snippet_that_needs_no_context_and_only_those_that_provide_it_to_one_that_does(): void
    {
        $client = self::signedInClient();
        $anyPage = self::createSnippet('Any page');
        $productPages = self::createSnippet('Product pages', context: ['product']);

        $offered = self::offeredSlots($client->request('GET', self::overview($anyPage)));
        self::assertCount(19, $offered);
        self::assertSame(['shop.top' => 'Every page: the top of the header', 'shop.before_content' => 'Every page: above the main content'], array_slice($offered, 0, 2, true));

        self::assertSame(['product.top', 'product.sidebar', 'product.after_price', 'product.before_tabs', 'product.details_tab', 'product.after_associations'], array_keys(self::offeredSlots($client->request('GET', self::overview($productPages)))));
    }

    /**
     * @test
     */
    public function it_adds_a_placement_in_the_slot_picked_after_the_slot_s_placements_and_comes_back_to_the_overview_page(): void
    {
        $client = self::signedInClient();
        self::place(self::createSnippet('Opening hours'), 'shop.footer');
        $snippet = self::createSnippet('Free shipping');
        $client->request('GET', self::overview($snippet));

        $client->submitForm('Add placement', ['slot' => 'shop.footer']);

        self::assertResponseRedirects(self::overview($snippet));
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.positive', 'The placement has been added. It shows in the shop at once.');
        self::assertSame([['shop.footer', 'Every page: the footer', '2 of 2']], self::rows($crawler));
        self::assertSame([['shop.footer', 1]], self::placementsOf($snippet));
    }

    /**
     * @test
     */
    public function it_dispatches_placements_changed_after_the_placement_is_stored_and_bumps_the_snippet_s_changed_at(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();
        $snippet = self::createSnippet('Free shipping');
        $uuid = $snippet->getUuid();
        $client->request('GET', self::overview($snippet));
        self::mockTime('2030-01-02 03:04:05 UTC');

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $outside = $connection->getTransactionNestingLevel();
        /** @var list<array{PlacementsChanged, int, mixed}> $dispatched the event, the transaction nesting level, and how many placements the snippet has */
        $dispatched = [];
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(PlacementsChanged::class, static function (PlacementsChanged $event) use (&$dispatched, $connection): void {
            $dispatched[] = [$event, $connection->getTransactionNestingLevel(), $connection->fetchOne('SELECT COUNT(*) FROM setono_sylius_cms__placement')];
        });

        $client->submitForm('Add placement', ['slot' => 'shop.footer']);

        self::assertCount(1, $dispatched);
        [[$event, $level, $count]] = $dispatched;
        self::assertSame([$uuid], $event->snippetUuids);
        self::assertSame(['shop.footer'], $event->slots);
        self::assertSame($outside, $level);
        self::assertEquals(1, $count);
        self::assertEquals(new \DateTimeImmutable('2030-01-02 03:04:05 UTC'), self::reloadSnippet($uuid)->getChangedAt());
    }

    /**
     * @test
     */
    public function it_moves_a_placement_up_past_another_snippet_s_placement_in_its_slot(): void
    {
        $client = self::signedInClient();
        $other = self::createSnippet('Opening hours');
        self::place($other, 'shop.footer');
        $snippet = self::createSnippet('Free shipping');
        self::place($snippet, 'shop.footer');
        $crawler = $client->request('GET', self::overview($snippet));
        self::assertNotNull($crawler->filter('[data-placement] button[name="direction"][value="down"]')->attr('disabled'), 'The last placement in its slot can\'t move down');
        self::assertNull($crawler->filter('[data-placement] button[name="direction"][value="up"]')->attr('disabled'));

        $client->submit($crawler->filter('[data-placement] button[name="direction"][value="up"]')->form());

        self::assertResponseRedirects(self::overview($snippet));
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.positive', 'The placement has been moved. The slot shows its placements in the new order at once.');
        self::assertSame([['shop.footer', 'Every page: the footer', '1 of 2']], self::rows($crawler));
        self::assertSame([['shop.footer', 0]], self::placementsOf($snippet));
        self::assertSame([['shop.footer', 1]], self::placementsOf($other));
    }

    /**
     * @test
     */
    public function it_removes_a_placement(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Free shipping');
        self::place($snippet, 'shop.footer');
        self::place($snippet, 'shop.top');
        $crawler = $client->request('GET', self::overview($snippet));
        $remove = $crawler->filter('[data-placement][data-slot="shop.top"] form[data-remove]');
        self::assertSame('Remove', trim($remove->filter('button')->text()));

        $client->submit($remove->form());

        self::assertResponseRedirects(self::overview($snippet));
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.positive', 'The placement has been removed. The snippet no longer shows there.');
        self::assertSame([['shop.footer', 'Every page: the footer', '1 of 1']], self::rows($crawler));
    }

    /**
     * @test
     */
    public function it_refuses_a_slot_that_does_not_provide_the_context_the_snippet_needs(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Size guide', context: ['product']);
        $crawler = $client->request('GET', self::overview($snippet));
        $form = $crawler->selectButton('Add placement')->form();
        $form->disableValidation();
        $form->setValues(['slot' => 'shop.footer']);

        $client->submit($form);

        self::assertResponseRedirects(self::overview($snippet));
        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'Pick a slot that provides the host-page context the snippet needs.');
        self::assertSame([], self::placementsOf($snippet));
    }

    /**
     * @test
     */
    public function it_flags_a_placement_in_a_slot_the_code_no_longer_declares_as_a_warning_and_lists_it_to_remove(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Free shipping');
        self::place($snippet, 'shop.footer');
        self::placeInUndeclaredSlot($snippet, 'shop.removed');
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($snippet), 'The lists and the Content badge count it');

        $crawler = $client->request('GET', self::overview($snippet));

        $problem = $crawler->filter('[data-section="problems"] [data-problem="removed_slot"]');
        self::assertCount(1, $problem);
        self::assertSame('warning', $problem->attr('data-severity'));
        self::assertSame('A placement in the slot "shop.removed", which the code no longer has, shows nothing.', $problem->filter('.description')->text());
        self::assertSame(self::overview($snippet) . '#cms-section-placements', $problem->filter('a')->attr('href'));

        self::assertSame([
            ['shop.footer', 'Every page: the footer', '1 of 1'],
            ['shop.removed', 'shop.removed', '1 of 1'],
        ], self::rows($crawler));
        self::assertSame('The code no longer has this slot, so the placement shows nothing. Remove it.', $crawler->filter('[data-slot="shop.removed"] [data-slot-removed]')->text());

        $client->submit($crawler->filter('[data-placement][data-slot="shop.removed"] form[data-remove]')->form());
        $crawler = $client->followRedirect();

        self::assertCount(0, $crawler->filter('[data-problem="removed_slot"]'));
        self::assertEquals(new ProblemCount(), self::storedCount($snippet));
    }

    /**
     * @test
     */
    public function it_refuses_a_form_without_the_page_s_csrf_token(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Free shipping');

        $client->request('POST', sprintf('%s/placements', self::overview($snippet)), ['slot' => 'shop.footer', '_csrf_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], self::placementsOf($snippet));
    }

    /**
     * @test
     */
    public function it_answers_404_for_a_placement_of_another_snippet(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Free shipping');
        $other = self::place(self::createSnippet('Opening hours'), 'shop.footer');
        $crawler = $client->request('GET', self::overview($snippet));
        $token = $crawler->filter('form[action$="/placements"] input[name="_csrf_token"]')->attr('value');

        $client->request('POST', sprintf('%s/placements/%s/remove', self::overview($snippet), $other->getUuid()), ['_csrf_token' => $token]);

        self::assertResponseStatusCodeSame(404);
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
     * Each placement the section lists: its slot's identifier, its slot's label and its position
     *
     * @return list<array{string, string, string}>
     */
    private static function rows(Crawler $crawler): array
    {
        return $crawler->filter('[data-section="placements"] [data-placement]')->each(static fn (Crawler $row): array => [
            (string) $row->attr('data-slot'),
            $row->filter('[data-slot-label]')->text(),
            $row->filter('[data-position]')->text(),
        ]);
    }

    /**
     * What Add placement offers: slot identifier => label
     *
     * @return array<string, string>
     */
    private static function offeredSlots(Crawler $crawler): array
    {
        $offered = [];
        $crawler->filter('[data-section="placements"] select[name="slot"] option')->each(static function (Crawler $option) use (&$offered): void {
            $offered[(string) $option->attr('value')] = $option->text();
        });

        return $offered;
    }

    private static function reloadSnippet(string $uuid): SnippetInterface
    {
        self::entityManager()->clear();
        $snippet = self::entityManager()->getRepository(SnippetInterface::class)->findOneBy(['uuid' => $uuid]);
        self::assertInstanceOf(SnippetInterface::class, $snippet);

        return $snippet;
    }
}
