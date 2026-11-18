<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Registry\EntryOrder;

final class EntryOrderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_parses_properties_with_their_directions(): void
    {
        $order = EntryOrder::parse(['city' => 'ASC', 'createdAt' => 'DESC']);

        self::assertSame(['city' => 'ASC', 'createdAt' => 'DESC'], $order->fields);
        self::assertFalse($order->byPosition);
    }

    /**
     * @test
     */
    public function it_parses_position_as_the_order_editors_drag_entries_into(): void
    {
        $order = EntryOrder::parse('position');

        self::assertTrue($order->byPosition);
        self::assertSame(['position' => 'ASC'], $order->fields);
    }

    /**
     * @test
     */
    public function it_orders_by_newest_first_by_default(): void
    {
        $order = EntryOrder::default();

        self::assertSame(['createdAt' => 'DESC'], $order->fields);
        self::assertFalse($order->byPosition);
    }

    /**
     * @test
     *
     * @dataProvider invalidOrders
     */
    public function it_refuses_an_order_that_is_neither_position_nor_properties_with_a_direction(mixed $order, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        EntryOrder::parse($order);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidOrders(): iterable
    {
        yield 'no properties' => [[], 'must name at least one property'];
        yield 'another string' => ['city', 'must be \'position\' or a map of property names to \'ASC\' or \'DESC\''];
        yield 'a list' => [['city'], 'must be \'position\' or a map of property names to \'ASC\' or \'DESC\''];
        yield 'a lowercase direction' => [['city' => 'asc'], 'orders "city" by "asc", which must be \'ASC\' or \'DESC\''];
        yield 'another direction' => [['city' => 'UP'], 'orders "city" by "UP", which must be \'ASC\' or \'DESC\''];
    }

    /**
     * @test
     */
    public function it_orders_a_query_by_its_properties_and_then_by_id(): void
    {
        $queryBuilder = new QueryBuilder($this->prophesize(EntityManagerInterface::class)->reveal());

        EntryOrder::parse(['city' => 'ASC', 'createdAt' => 'DESC'])->applyTo($queryBuilder, 'entry');

        /** @var list<OrderBy> $orderBy */
        $orderBy = $queryBuilder->getDQLPart('orderBy');
        self::assertSame(['entry.city ASC', 'entry.createdAt DESC', 'entry.id ASC'], array_merge(...array_map(static fn (OrderBy $part): array => $part->getParts(), $orderBy)));
    }
}
