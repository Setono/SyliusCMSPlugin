<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\FaqItem;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Registry\EntryOrder;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Location;

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

    /**
     * Related lists newest first: by the content type's order when it's a date descending, such as Blog post's
     * publishedAt, and else by createdAt descending (docs/spec/sources.md#entry-sources)
     *
     * @test
     */
    public function it_is_newest_first_by_a_date_descending_it_orders_by_first(): void
    {
        $order = EntryOrder::parse(['publishedAt' => 'DESC', 'title' => 'ASC'])->newestFirst(BlogPost::class);

        self::assertSame(['publishedAt' => 'DESC', 'title' => 'ASC'], $order->fields);
        self::assertSame(['createdAt' => 'DESC'], EntryOrder::parse(['createdAt' => 'DESC'])->newestFirst(Page::class)->fields);
    }

    /**
     * @test
     */
    public function it_is_newest_first_by_the_creation_date_for_an_order_that_is_not_a_date_descending(): void
    {
        self::assertSame(['createdAt' => 'DESC'], EntryOrder::parse(ContentType::ORDER_BY_POSITION)->newestFirst(FaqItem::class)->fields, 'By position');
        self::assertSame(['createdAt' => 'DESC'], EntryOrder::parse(['publishedAt' => 'ASC'])->newestFirst(BlogPost::class)->fields, 'A date ascending is oldest first');
        self::assertSame(['createdAt' => 'DESC'], EntryOrder::parse(['city' => 'DESC'])->newestFirst(Location::class)->fields, 'A property that isn\'t a date');
        self::assertSame(['createdAt' => 'DESC'], EntryOrder::parse(['missing' => 'DESC'])->newestFirst(Page::class)->fields, 'A property the model doesn\'t have');
        self::assertFalse(EntryOrder::parse(ContentType::ORDER_BY_POSITION)->newestFirst(FaqItem::class)->byPosition);
    }
}
