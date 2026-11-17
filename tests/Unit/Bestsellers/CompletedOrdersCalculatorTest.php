<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Bestsellers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Bestsellers\CompletedOrdersCalculator;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Order;
use Symfony\Component\Clock\MockClock;

/**
 * The default bestsellers calculator: a product's score in a channel is how many of the channel's orders whose
 * checkout completed inside bestsellers.window contained it, whatever their payment state. It counts in SQL, so its
 * query is what's checked here, and the functional suite counts real orders. See "Bestsellers" in docs/spec/sources.md
 */
final class CompletedOrdersCalculatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_counts_the_orders_of_the_channel_that_contained_each_product_each_order_once_whatever_their_payment_state(): void
    {
        $channel = self::channel();
        $queryBuilder = $this->calculator()->createQueryBuilder($channel);

        self::assertSame(sprintf(
            'SELECT product.code AS productCode, COUNT(DISTINCT o.id) AS orders FROM %s o INNER JOIN o.items item INNER JOIN item.variant variant INNER JOIN variant.product product WHERE o.channel = :channel AND o.checkoutCompletedAt >= :since AND o.checkoutCompletedAt <= :until GROUP BY product.code',
            Order::class,
        ), $queryBuilder->getDQL());
        self::assertSame($channel, $queryBuilder->getParameter('channel')?->getValue());
    }

    /**
     * @test
     */
    public function it_counts_only_the_orders_whose_checkout_completed_inside_the_window_up_to_now(): void
    {
        $queryBuilder = $this->calculator('P30D')->createQueryBuilder(self::channel());

        self::assertEquals(new \DateTimeImmutable('2026-10-18 12:00:00'), $queryBuilder->getParameter('since')?->getValue());
        self::assertEquals(new \DateTimeImmutable('2026-11-17 12:00:00'), $queryBuilder->getParameter('until')?->getValue());
    }

    /**
     * @test
     */
    public function it_counts_over_the_window_a_project_sets(): void
    {
        $queryBuilder = $this->calculator('PT12H')->createQueryBuilder(self::channel());

        self::assertEquals(new \DateTimeImmutable('2026-11-17 00:00:00'), $queryBuilder->getParameter('since')?->getValue());
    }

    /**
     * @test
     */
    public function it_refuses_a_window_that_is_not_an_iso_8601_duration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"30 days" isn\'t an ISO 8601 duration, such as P1D');

        $this->calculator('30 days');
    }

    private function calculator(string $window = 'P30D'): CompletedOrdersCalculator
    {
        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getExpressionBuilder()->willReturn(new Expr());
        $entityManager->createQueryBuilder()->will(static fn (): QueryBuilder => new QueryBuilder($entityManager->reveal()));

        return new CompletedOrdersCalculator($entityManager->reveal(), Order::class, new MockClock('2026-11-17 12:00:00'), $window);
    }

    private static function channel(): Channel
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return $channel;
    }
}
