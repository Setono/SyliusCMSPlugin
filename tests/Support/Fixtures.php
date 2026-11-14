<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Factory\ChannelFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates the data that functional and UI tests need, through the test application's own services.
 * Every test runs in a transaction that is rolled back afterwards, so nothing created here outlives the test.
 */
final readonly class Fixtures
{
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * Creates an enabled channel on localhost with the en_US locale and the USD currency,
     * which is all the shop needs to render its pages
     */
    public function createChannel(string $name): ChannelInterface
    {
        /** @var FactoryInterface<LocaleInterface> $localeFactory */
        $localeFactory = $this->service('sylius.factory.locale', FactoryInterface::class);
        $locale = $localeFactory->createNew();
        $locale->setCode('en_US');

        /** @var FactoryInterface<CurrencyInterface> $currencyFactory */
        $currencyFactory = $this->service('sylius.factory.currency', FactoryInterface::class);
        $currency = $currencyFactory->createNew();
        $currency->setCode('USD');

        $channel = $this->service('sylius.factory.channel', ChannelFactoryInterface::class)->createNamed($name);
        if (!$channel instanceof ChannelInterface) {
            throw new \LogicException(sprintf('The channel factory must create an instance of %s', ChannelInterface::class));
        }

        $channel->setCode('WEB');
        $channel->setHostname('localhost');
        $channel->setEnabled(true);
        $channel->setTaxCalculationStrategy('order_items_based');
        $channel->addLocale($locale);
        $channel->setDefaultLocale($locale);
        $channel->addCurrency($currency);
        $channel->setBaseCurrency($currency);

        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $entityManager->persist($locale);
        $entityManager->persist($currency);
        $entityManager->persist($channel);
        $entityManager->flush();

        return $channel;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = $this->container->get($id);
        if (!$service instanceof $class) {
            throw new \LogicException(sprintf('The service "%s" must be an instance of %s', $id, $class));
        }

        return $service;
    }
}
