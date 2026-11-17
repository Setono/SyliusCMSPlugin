<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SlotsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * shop.top, which SlotsKernel caps at 1 as a project caps a stock slot: only its first matching placement shows. See
 * "Capped slots" in docs/spec/slots.md and docs/spec/targeting.md
 */
final class CappedSlotsTest extends WebTestCase
{
    use Customers;
    use Entries;
    use Placements;

    protected static function getKernelClass(): string
    {
        return SlotsKernel::class;
    }

    /**
     * @test
     */
    public function it_shows_only_the_first_of_two_matching_placements_in_a_slot_capped_at_1(): void
    {
        $client = self::createClient();
        self::place(self::createSnippet('Free shipping', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Free shipping')], published: true), 'shop.top');
        self::place(self::createSnippet('Winter sale', [self::heading('0193a5be-0000-7000-8000-000000000002', 'Winter sale')], published: true), 'shop.top');

        $crawler = $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        self::assertSame(['Free shipping'], self::headingsIn($crawler, '.cms-slot--shop-top'));
    }

    /**
     * A placement matches when its snippet is visible: an unpublished one, or one whose audience leaves the shopper out,
     * doesn't take the slot, so the next one wins. See ADR 0010
     *
     * @test
     */
    public function it_lets_the_next_placement_win_when_the_first_one_s_snippet_is_not_visible_to_the_shopper(): void
    {
        $client = self::createClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        self::place(self::createSnippet('Draft', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Draft')]), 'shop.top');
        $wholesale = self::createSnippet('Wholesale', [self::heading('0193a5be-0000-7000-8000-000000000002', 'Wholesale prices')], published: true);
        self::channelsAndAudience()->setAudience($wholesale, Audience::only('wholesale'));
        self::entityManager()->flush();
        self::place($wholesale, 'shop.top');
        self::place(self::createSnippet('Welcome', [self::heading('0193a5be-0000-7000-8000-000000000003', 'Welcome')], published: true), 'shop.top');

        self::assertSame(['Welcome'], self::headingsIn($client->request('GET', '/en_US/'), '.cms-slot--shop-top'), 'A guest gets the catch-all');

        $client->loginUser(self::createShopUser('wholesaler@example.com', 'wholesale'), 'shop');
        self::assertSame(['Wholesale prices'], self::headingsIn($client->request('GET', '/en_US/'), '.cms-slot--shop-top'));
    }

    /**
     * The winner is picked without rendering, so a snippet whose only block is hidden still takes the slot, and the
     * slot shows nothing
     *
     * @test
     */
    public function it_shows_nothing_when_the_winning_snippet_s_blocks_are_all_hidden(): void
    {
        $client = self::createClient();
        $hidden = self::heading('0193a5be-0000-7000-8000-000000000001', 'Danish only');
        $hidden->conditions = [['kind' => 'locale', 'negate' => false, 'params' => ['locales' => ['da_DK']]]];
        self::place(self::createSnippet('Danish only', [$hidden], published: true), 'shop.top');
        self::place(self::createSnippet('Welcome', [self::heading('0193a5be-0000-7000-8000-000000000002', 'Welcome')], published: true), 'shop.top');

        $crawler = $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.cms-slot--shop-top'));
    }

    /**
     * @return list<string>
     */
    private static function headingsIn(Crawler $crawler, string $slot): array
    {
        return $crawler->filter($slot . ' .cms-heading')->each(static fn (Crawler $heading): string => $heading->text());
    }
}
