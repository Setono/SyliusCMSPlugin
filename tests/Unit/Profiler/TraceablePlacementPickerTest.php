<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Profiler;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Profiler\RenderedSlots;
use Setono\SyliusCMSPlugin\Profiler\TraceablePlacementPicker;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Rendering\CandidatePlacement;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Setono\SyliusCMSPlugin\Rendering\PlacementOutcome;
use Setono\SyliusCMSPlugin\Rendering\PlacementPickerInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;

final class TraceablePlacementPickerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_records_every_candidate_placement_of_the_slot_rendering_now_and_what_it_picked_in(): void
    {
        $slot = new SlotMetadata('shop.footer', 'setono_sylius_cms.slot.shop.footer');
        $placements = [self::placement('0193a5be-0000-7000-8000-000000000001'), self::placement('0193a5be-0000-7000-8000-000000000002')];
        $context = Rendering::context();
        $candidates = [
            new CandidatePlacement($placements[0], PlacementOutcome::SnippetNotVisible),
            new CandidatePlacement($placements[1], PlacementOutcome::Wins, new Snippet()),
        ];
        $picker = $this->prophesize(PlacementPickerInterface::class);
        $picker->pick($slot, $placements, $context)->willReturn($candidates);
        $slots = new RenderedSlots();
        $trace = $slots->start('shop.footer');

        self::assertSame($candidates, (new TraceablePlacementPicker($picker->reveal(), $slots))->pick($slot, $placements, $context));
        self::assertSame($candidates, $trace->candidates);
        self::assertSame($context, $trace->renderContext);
    }

    /**
     * Such as a preview asking which placement wins in another slot
     *
     * @test
     */
    public function it_records_nothing_for_another_slot_than_the_one_rendering_now(): void
    {
        $slot = new SlotMetadata('shop.top', 'setono_sylius_cms.slot.shop.top');
        $context = Rendering::context();
        $picker = $this->prophesize(PlacementPickerInterface::class);
        $picker->pick($slot, [], $context)->willReturn([]);
        $slots = new RenderedSlots();
        $trace = $slots->start('shop.footer');

        (new TraceablePlacementPicker($picker->reveal(), $slots))->pick($slot, [], $context);

        self::assertNull($trace->candidates);
    }

    private static function placement(string $uuid): PlacementData
    {
        return new PlacementData($uuid, '0193a5be-0000-7000-8000-0000000000aa', 'shop.footer', [], 0);
    }
}
