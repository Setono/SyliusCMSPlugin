<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helpers for functional and UI tests that need orders, which the test fixtures don't have, such as the completed
 * orders the default bestsellers calculator counts. An order is written as it is once its checkout completed, in the
 * states the test gives it, without going through Sylius's checkout
 */
trait Orders
{
    private static int $orderNumber = 0;

    abstract protected static function getContainer(): ContainerInterface;

    /**
     * An order of one of each of the products' first variants, whose checkout completed at the time, by default now
     *
     * @param list<ProductInterface> $products
     */
    protected static function createOrder(
        array $products,
        string $paymentState = OrderPaymentStates::STATE_PAID,
        ?\DateTimeInterface $checkoutCompletedAt = null,
        string $channelCode = 'WEB',
    ): OrderInterface {
        /** @var FactoryInterface<OrderInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.order');
        $order = $factory->createNew();
        $order->setChannel(self::orderChannel($channelCode));
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->setNumber(sprintf('CMS%06d', ++self::$orderNumber));

        /** @var FactoryInterface<OrderItemInterface> $itemFactory */
        $itemFactory = self::getContainer()->get('sylius.factory.order_item');
        /** @var OrderItemQuantityModifierInterface $quantityModifier */
        $quantityModifier = self::getContainer()->get('sylius.order_item_quantity_modifier');
        foreach ($products as $product) {
            $variant = $product->getVariants()->first();
            self::assertInstanceOf(ProductVariantInterface::class, $variant);

            $item = $itemFactory->createNew();
            $item->setVariant($variant);
            $item->setUnitPrice(1000);
            $quantityModifier->modify($item, 1);
            $order->addItem($item);
        }

        $order->setState(BaseOrderInterface::STATE_NEW);
        $order->setCheckoutState(OrderCheckoutStates::STATE_COMPLETED);
        $order->setCheckoutCompletedAt($checkoutCompletedAt ?? new \DateTime());
        $order->setPaymentState($paymentState);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.order');
        $manager->persist($order);
        $manager->flush();

        return $order;
    }

    private static function orderChannel(string $code): ChannelInterface
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneByCode($code);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }
}
