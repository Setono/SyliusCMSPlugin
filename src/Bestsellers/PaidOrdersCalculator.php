<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Bestsellers;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\OrderPaymentStates;

/**
 * The default bestsellers calculator: a product's score in a channel is how many of the channel's paid orders
 * contained it, counting each order once, over a rolling window, bestsellers.window. An order counts when its checkout
 * completed inside the window and its payment state is paid. It counts in SQL, a row per product sold.
 * See "Bestsellers" in docs/spec/sources.md
 */
final readonly class PaidOrdersCalculator implements BestsellersCalculatorInterface
{
    private \DateInterval $window;

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
        try {
            $this->window = new \DateInterval($window);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('"%s" isn\'t an ISO 8601 duration, such as P30D', $window), previous: $e);
        }
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
     * The query calculate() runs: the code of each product the channel's paid orders inside the window contained, as
     * productCode, and how many of them did, as orders. The order's alias is "o", and its item's, variant's and
     * product's are "item", "variant" and "product", so a project's calculator can build on it
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
            ->andWhere('o.paymentState = :paymentState')
            ->andWhere('o.checkoutCompletedAt >= :since')
            ->andWhere('o.checkoutCompletedAt <= :until')
            ->groupBy('product.code')
            ->setParameter('channel', $channel)
            ->setParameter('paymentState', OrderPaymentStates::STATE_PAID)
            ->setParameter('since', $until->sub($this->window), Types::DATETIME_IMMUTABLE)
            ->setParameter('until', $until, Types::DATETIME_IMMUTABLE)
        ;
    }
}
