<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Bestsellers;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Time\Duration;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * The default bestsellers calculator: a product's score in a channel is how many of the channel's orders contained it,
 * counting each order once, over a rolling window, bestsellers.window. An order counts when its checkout completed
 * inside the window, whatever its payment state. It counts in SQL, a row per product sold. A project that counts only
 * some orders, such as the paid ones, builds on createQueryBuilder(). See "Bestsellers" in docs/spec/sources.md
 */
final readonly class CompletedOrdersCalculator implements BestsellersCalculatorInterface
{
    private Duration $window;

    /**
     * @param EntityManagerInterface $orderManager the manager of orders
     * @param class-string $orderClass
     * @param string $window an ISO 8601 duration, such as P30D
     *
     * @throws \InvalidArgumentException when $window isn't an ISO 8601 duration
     */
    public function __construct(
        private EntityManagerInterface $orderManager,
        private string $orderClass,
        private ClockInterface $clock,
        string $window,
    ) {
        $this->window = Duration::fromIso8601($window);
    }

    public function calculate(ChannelInterface $channel): iterable
    {
        /** @var list<array{productCode: string, orders: int|string}> $rows */
        $rows = $this->createQueryBuilder($channel)->getQuery()->getScalarResult();

        foreach ($rows as $row) {
            yield $row['productCode'] => (int) $row['orders'];
        }
    }

    /**
     * The query calculate() runs: the code of each product that the channel's orders completed inside the window
     * contained, as productCode, and how many of them did, as orders. The order's alias is "o", and its item's,
     * variant's and product's are "item", "variant" and "product", so a project's calculator can build on it, such as
     * by adding a condition on o.paymentState
     */
    public function createQueryBuilder(ChannelInterface $channel): QueryBuilder
    {
        $until = $this->clock->now();

        return $this->orderManager->createQueryBuilder()
            ->select('product.code AS productCode', 'COUNT(DISTINCT o.id) AS orders')
            ->from($this->orderClass, 'o')
            ->innerJoin('o.items', 'item')
            ->innerJoin('item.variant', 'variant')
            ->innerJoin('variant.product', 'product')
            ->andWhere('o.channel = :channel')
            ->andWhere('o.checkoutCompletedAt >= :since')
            ->andWhere('o.checkoutCompletedAt <= :until')
            ->groupBy('product.code')
            ->setParameter('channel', $channel)
            ->setParameter('since', $this->window->before($until), Types::DATETIME_IMMUTABLE)
            ->setParameter('until', $until, Types::DATETIME_IMMUTABLE)
        ;
    }
}
