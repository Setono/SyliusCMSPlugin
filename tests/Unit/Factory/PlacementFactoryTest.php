<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Factory\PlacementFactory;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Tests\Unit\Factory\Fixture\ProjectPlacement;
use Sylius\Resource\Exception\UnsupportedMethodException;

final class PlacementFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_a_placement_of_the_configured_model_of_a_snippet_in_a_slot_at_a_position(): void
    {
        $snippet = new Snippet();

        $placement = (new PlacementFactory(ProjectPlacement::class))->createFor($snippet, 'shop.footer', 2);

        self::assertInstanceOf(ProjectPlacement::class, $placement);
        self::assertSame($snippet, $placement->getSnippet());
        self::assertSame('shop.footer', $placement->getSlot());
        self::assertSame(2, $placement->getPosition());
    }

    /**
     * A placement always shows a snippet in a slot
     *
     * @test
     */
    public function it_creates_a_placement_only_of_a_snippet_in_a_slot(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        (new PlacementFactory(ProjectPlacement::class))->createNew();
    }
}
