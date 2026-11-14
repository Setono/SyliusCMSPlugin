<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Helpers for functional and UI tests that need customer groups and signed-in customers, made with Sylius's example factories
 */
trait Customers
{
    abstract protected static function getContainer(): ContainerInterface;

    protected static function createCustomerGroup(string $code, string $name): CustomerGroupInterface
    {
        /** @var ExampleFactoryInterface $factory */
        $factory = self::getContainer()->get('sylius.fixture.example_factory.customer_group');
        $group = $factory->create(['code' => $code, 'name' => $name]);
        self::assertInstanceOf(CustomerGroupInterface::class, $group);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.customer_group');
        $manager->persist($group);
        $manager->flush();

        return $group;
    }

    /**
     * A shop user whose customer is in the customer group with this code, or in none
     */
    protected static function createShopUser(string $email, ?string $customerGroup = null): ShopUserInterface&UserInterface
    {
        /** @var ExampleFactoryInterface $factory */
        $factory = self::getContainer()->get('sylius.fixture.example_factory.shop_user');
        $user = $factory->create(['email' => $email, 'customer_group' => $customerGroup]);
        self::assertInstanceOf(ShopUserInterface::class, $user);
        self::assertInstanceOf(UserInterface::class, $user);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.shop_user');
        $manager->persist($user);
        $manager->flush();

        return $user;
    }
}
