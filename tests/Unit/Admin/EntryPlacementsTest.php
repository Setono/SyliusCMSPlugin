<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\EntryPlacement;
use Setono\SyliusCMSPlugin\Admin\EntryPlacements;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;

final class EntryPlacementsTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<PlacementRepositoryInterface> */
    private ObjectProphecy $placements;

    protected function setUp(): void
    {
        $this->placements = $this->prophesize(PlacementRepositoryInterface::class);
    }

    /**
     * @test
     */
    public function it_lists_a_snippet_s_placements_in_the_order_the_slots_are_declared_with_each_one_s_place_among_its_slot_s_placements(): void
    {
        $snippet = new Snippet();
        $other = new Snippet();
        $inFooter = new Placement($snippet, 'shop.footer', 4);
        $inTop = new Placement($snippet, 'shop.top', 0);
        $othersInFooter = new Placement($other, 'shop.footer', 1);
        $secondInFooter = new Placement($snippet, 'shop.footer', 7);
        $this->placements->findBySnippet($snippet)->willReturn([$inFooter, $secondInFooter, $inTop]);
        $this->placements->findBySlots(['shop.footer', 'shop.top'])->willReturn([$othersInFooter, $inFooter, $secondInFooter, $inTop]);

        $section = $this->entryPlacements()->of($snippet);

        self::assertNotNull($section);
        self::assertSame([
            ['shop.top', 1, 1, true, true],
            ['shop.footer', 2, 3, false, false],
            ['shop.footer', 3, 3, false, true],
        ], array_map(static fn (EntryPlacement $placement): array => [$placement->slot?->identifier, $placement->position, $placement->count, $placement->first, $placement->last], $section->placements));
        self::assertSame($inTop, $section->placements[0]->placement);
    }

    /**
     * @test
     */
    public function it_lists_a_placement_in_a_slot_the_code_no_longer_declares_last_without_its_slot(): void
    {
        $snippet = new Snippet();
        $removed = new Placement($snippet, 'shop.removed', 0);
        $inFooter = new Placement($snippet, 'shop.footer', 0);
        $this->placements->findBySnippet($snippet)->willReturn([$inFooter, $removed]);
        $this->placements->findBySlots(['shop.footer', 'shop.removed'])->willReturn([$inFooter, $removed]);

        $section = $this->entryPlacements()->of($snippet);

        self::assertNotNull($section);
        self::assertSame([$inFooter, $removed], array_map(static fn (EntryPlacement $placement): object => $placement->placement, $section->placements));
        self::assertNull($section->placements[1]->slot);
    }

    /**
     * @test
     */
    public function it_offers_the_slots_that_provide_at_least_the_context_the_snippet_needs(): void
    {
        $anyPage = new Snippet();
        $productPages = new Snippet();
        $productPages->setContext(['product']);
        $this->placements->findBySnippet($anyPage)->willReturn([]);
        $this->placements->findBySnippet($productPages)->willReturn([]);
        $this->placements->findBySlots([])->willReturn([]);
        $identifiers = static fn (array $slots): array => array_map(static fn (SlotMetadata $slot): string => $slot->identifier, $slots);

        self::assertSame(['shop.top', 'shop.footer', 'product.after_price'], $identifiers($this->entryPlacements()->of($anyPage)?->offeredSlots ?? []));
        self::assertSame(['product.after_price'], $identifiers($this->entryPlacements()->of($productPages)?->offeredSlots ?? []));
    }

    /**
     * @test
     */
    public function it_has_no_placements_section_for_an_entry_that_is_not_a_snippet(): void
    {
        self::assertNull($this->entryPlacements()->of(new Page()));
    }

    private function entryPlacements(): EntryPlacements
    {
        return new EntryPlacements($this->placements->reveal(), new SlotRegistry([
            'shop.top' => ['label' => 'setono_sylius_cms.slot.shop.top', 'provides' => [], 'cap' => null],
            'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null],
            'product.after_price' => ['label' => 'setono_sylius_cms.slot.product.after_price', 'provides' => ['product'], 'cap' => null],
        ]));
    }
}
