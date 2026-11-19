<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Transfer\PrecedingPlacements;

/**
 * What an export records of where a snippet's placement sits in its slot: the placements before it in the source slot's
 * order, from every snippet, nearest first. See "Placements" in docs/spec/transfer.md
 */
final class PrecedingPlacementsTest extends TestCase
{
    /**
     * @test
     */
    public function it_lists_the_placements_before_each_one_in_its_slot_nearest_first(): void
    {
        $first = new Placement(new Snippet(), 'product.after_price', 0);
        $second = new Placement(new Snippet(), 'product.after_price', 3);
        $third = new Placement(new Snippet(), 'product.after_price', 7);

        $preceding = PrecedingPlacements::of([$first, $second, $third]);

        self::assertSame([], $preceding[$first->getUuid()]);
        self::assertSame([$first->getUuid()], $preceding[$second->getUuid()]);
        self::assertSame([$second->getUuid(), $first->getUuid()], $preceding[$third->getUuid()]);
    }

    /**
     * @test
     */
    public function it_orders_each_slot_by_position_whatever_order_the_placements_come_in(): void
    {
        $last = new Placement(new Snippet(), 'shop.footer', 5);
        $first = new Placement(new Snippet(), 'shop.footer', 1);
        $middle = new Placement(new Snippet(), 'shop.footer', 2);

        self::assertSame([$middle->getUuid(), $first->getUuid()], PrecedingPlacements::of([$last, $first, $middle])[$last->getUuid()]);
    }

    /**
     * @test
     */
    public function it_never_counts_a_placement_in_another_slot_as_preceding(): void
    {
        $footer = new Placement(new Snippet(), 'shop.footer', 0);
        $price = new Placement(new Snippet(), 'product.after_price', 4);
        $laterFooter = new Placement(new Snippet(), 'shop.footer', 1);

        $preceding = PrecedingPlacements::of([$footer, $price, $laterFooter]);

        self::assertSame([], $preceding[$price->getUuid()]);
        self::assertSame([$footer->getUuid()], $preceding[$laterFooter->getUuid()]);
    }
}
