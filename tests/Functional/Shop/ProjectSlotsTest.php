<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SlotsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Html;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Slots a project declares: one rendered from its own template with cms_slot(), and one tied to a template event.
 * See "Slots" and "Rendering a slot" in docs/spec/slots.md
 */
final class ProjectSlotsTest extends WebTestCase
{
    use Entries;
    use Placements;

    protected static function getKernelClass(): string
    {
        return SlotsKernel::class;
    }

    /**
     * @test
     */
    public function it_renders_a_slot_a_project_template_renders_with_cms_slot(): void
    {
        $client = self::createClient();
        self::place(self::createSnippet('Find us', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Find a store near you')], published: true), 'app.store_locator.top');

        $crawler = $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        self::assertSame(
            '<section class="app-store-locator"><div class="cms-slot cms-slot--app-store-locator-top"><h2 class="cms-heading">Find a store near you</h2></div></section>',
            Html::withoutWhitespaceBetweenTags($crawler->filter('.app-store-locator')->outerHtml()),
        );
    }

    /**
     * @test
     */
    public function it_renders_a_slot_a_project_ties_to_a_template_event_there_at_its_priority(): void
    {
        $client = self::createClient();
        self::place(self::createSnippet('Sale', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Winter sale')], published: true), 'app.announcements');
        self::place(self::createSnippet('Top', [self::heading('0193a5be-0000-7000-8000-000000000002', 'Free returns')], published: true), 'shop.top');

        $crawler = $client->request('GET', '/en_US/');

        // Both on the shop layout's header event, the project's slot (40) before shop.top (30)
        self::assertSame(['Winter sale', 'Free returns'], $crawler->filter('.cms-slot .cms-heading')->each(static fn ($heading): string => $heading->text()));
        self::assertSame(['cms-slot cms-slot--app-announcements', 'cms-slot cms-slot--shop-top'], $crawler->filter('.cms-slot')->each(static fn ($slot): string => (string) $slot->attr('class')));
    }

    /**
     * An entry the CMS renders provides itself to its layout's slots, which get it as cms_slot(id, {entry: entry})
     *
     * @test
     */
    public function it_gives_the_slots_of_an_entry_s_layout_the_entry_being_shown(): void
    {
        $client = self::createClient();
        $page = self::createPage('Winter sale', 'winter-sale', 'Everything must go', published: true);
        $page->setBlockTree(new StoredTree(new StoredLayout('promo', 1), $page->getBlockTree()->blocks));
        self::entityManager()->flush();
        $snippet = self::createSnippet('About the entry', [self::heading('0193a5be-0000-7000-8000-000000000001', 'More like this')], published: true, context: ['entry']);
        self::place($snippet, 'app.promo.after_blocks');

        $crawler = $client->request('GET', '/en_US/winter-sale');

        self::assertResponseIsSuccessful();
        self::assertSame(['More like this'], $crawler->filter('.cms-slot--app-promo-after-blocks .cms-heading')->each(static fn ($heading): string => $heading->text()));
        self::assertSame(['app.promo.after_blocks'], array_map(static fn (SlotMetadata $slot): string => $slot->identifier, self::slots()->providing(['entry'])));
    }

    /**
     * @test
     */
    public function it_declares_the_stock_slots_before_the_project_s_own_and_lets_a_project_cap_a_stock_slot(): void
    {
        self::bootKernel();
        $slots = self::slots();

        $identifiers = array_map(static fn (SlotMetadata $slot): string => $slot->identifier, $slots->all());
        self::assertCount(22, $identifiers);
        self::assertSame(['shop.top', 'shop.before_content', 'shop.after_content', 'shop.footer'], array_slice($identifiers, 0, 4));
        self::assertSame(['app.store_locator.top', 'app.announcements', 'app.promo.after_blocks'], array_slice($identifiers, -3));

        self::assertEquals(new SlotMetadata('shop.top', 'setono_sylius_cms.slot.shop.top', [], 1, 'sylius.shop.layout.header', 30), $slots->get('shop.top'));
        self::assertEquals(new SlotMetadata('app.store_locator.top', 'app.cms.slot.store_locator_top'), $slots->get('app.store_locator.top'));
    }

    private static function slots(): SlotRegistryInterface
    {
        $slots = self::getContainer()->get(SlotRegistryInterface::class);
        self::assertInstanceOf(SlotRegistryInterface::class, $slots);

        return $slots;
    }
}
