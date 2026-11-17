<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;

final class PlacementDataTest extends TestCase
{
    /**
     * Placements at the same position, such as two added at once, keep the order the provider lists them in
     *
     * @test
     */
    public function it_gives_the_placements_in_a_slot_in_editor_order(): void
    {
        $second = new PlacementData('0193a5be-0000-7000-8000-000000000002', 'snippet', 'shop.top', [], 1);
        $first = new PlacementData('0193a5be-0000-7000-8000-000000000001', 'snippet', 'shop.top', [], 0);
        $alsoSecond = new PlacementData('0193a5be-0000-7000-8000-000000000003', 'snippet', 'shop.top', [], 1);
        $footer = new PlacementData('0193a5be-0000-7000-8000-000000000004', 'snippet', 'shop.footer', [], 0);

        self::assertSame([$first, $second, $alsoSecond], PlacementData::inSlot([$second, $footer, $first, $alsoSecond], 'shop.top'));
        self::assertSame([], PlacementData::inSlot([$footer], 'shop.top'));
    }
}
