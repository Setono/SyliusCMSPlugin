<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class PlacementTest extends TestCase
{
    /**
     * @test
     */
    public function it_shows_a_snippet_in_a_slot_at_a_position_with_an_empty_page_scope(): void
    {
        $snippet = new Snippet();

        $placement = new Placement($snippet, 'shop.footer', 3);

        self::assertSame($snippet, $placement->getSnippet());
        self::assertSame('shop.footer', $placement->getSlot());
        self::assertSame(3, $placement->getPosition());
        self::assertSame([], $placement->getPageScope());
        self::assertNull($placement->getId());
    }

    /**
     * @test
     */
    public function it_gets_a_lowercase_uuid_v7(): void
    {
        $placement = new Placement(new Snippet(), 'shop.footer', 0);

        self::assertInstanceOf(UuidV7::class, Uuid::fromString($placement->getUuid()));
        self::assertSame(strtolower($placement->getUuid()), $placement->getUuid());
        self::assertNotSame((new Placement(new Snippet(), 'shop.footer', 0))->getUuid(), $placement->getUuid());
    }

    /**
     * @test
     */
    public function it_moves_to_another_position_in_its_slot(): void
    {
        $placement = new Placement(new Snippet(), 'shop.footer', 0);

        $placement->setPosition(2);

        self::assertSame(2, $placement->getPosition());
    }
}
