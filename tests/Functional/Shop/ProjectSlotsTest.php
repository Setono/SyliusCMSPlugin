<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SlotsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Html;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
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
     * @test
     */
    public function it_declares_the_stock_slots_before_the_project_s_own_and_lets_a_project_cap_a_stock_slot(): void
    {
        self::bootKernel();
        $slots = self::getContainer()->get(SlotRegistryInterface::class);
        self::assertInstanceOf(SlotRegistryInterface::class, $slots);

        $identifiers = array_map(static fn (SlotMetadata $slot): string => $slot->identifier, $slots->all());
        self::assertCount(21, $identifiers);
        self::assertSame(['shop.top', 'shop.before_content', 'shop.after_content', 'shop.footer'], array_slice($identifiers, 0, 4));
        self::assertSame(['app.store_locator.top', 'app.announcements'], array_slice($identifiers, -2));

        self::assertEquals(new SlotMetadata('shop.top', 'setono_sylius_cms.slot.shop.top', [], 1, 'sylius.shop.layout.header', 30), $slots->get('shop.top'));
        self::assertEquals(new SlotMetadata('app.store_locator.top', 'app.cms.slot.store_locator_top'), $slots->get('app.store_locator.top'));
    }
}
