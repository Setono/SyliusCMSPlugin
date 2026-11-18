<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
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

    /**
     * @test
     */
    public function it_is_limited_by_a_page_scope(): void
    {
        $placement = new Placement(new Snippet(), 'product.after_price', 0);
        $scope = [['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['taxons' => ['SHOES'], 'includeChildren' => true]]];

        $placement->setPageScope($scope);

        self::assertSame($scope, $placement->getPageScope());
    }

    /**
     * A capped slot's fallback: a placement with an empty page scope whose snippet is published, enabled and for
     * everyone, wherever it sits in the slot. See "Capped slots" in docs/spec/targeting.md
     *
     * @test
     */
    public function it_is_a_catch_all_with_an_empty_page_scope_and_a_published_enabled_snippet_for_everyone(): void
    {
        $forEveryone = self::published(new Snippet());
        $forWholesale = self::published(new Snippet());
        ChannelsAndAudience::modifier()->setAudience($forWholesale, Audience::only('wholesale'));
        $scoped = new Placement($forEveryone, 'product.after_price', 0);
        $scoped->setPageScope([['kind' => 'product', 'negate' => true, 'params' => ['products' => ['MUG']]]]);
        $disabled = self::published(new Snippet());
        $disabled->setEnabled(false);

        self::assertTrue((new Placement($forEveryone, 'shop.top', 3))->isCatchAll());
        self::assertFalse((new Placement($forWholesale, 'shop.top', 0))->isCatchAll());
        self::assertFalse($scoped->isCatchAll());
        self::assertFalse((new Placement(new Snippet(), 'shop.top', 0))->isCatchAll(), 'Its snippet was never published');
        self::assertFalse((new Placement($disabled, 'shop.top', 0))->isCatchAll(), 'Its snippet is disabled');
    }

    private static function published(Snippet $snippet): Snippet
    {
        $snippet->publish(new \DateTimeImmutable('2026-11-18 10:00:00'));

        return $snippet;
    }
}
