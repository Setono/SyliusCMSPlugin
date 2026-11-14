<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Helpers for functional and UI tests that need admin users besides "sylius" (John Doe), made with Sylius's example factory
 */
trait Admins
{
    abstract protected static function getContainer(): ContainerInterface;

    protected static function createAdmin(string $username, string $firstName, string $lastName): AdminUserInterface&UserInterface
    {
        /** @var ExampleFactoryInterface $factory */
        $factory = self::getContainer()->get('sylius.fixture.example_factory.admin_user');
        $admin = $factory->create([
            'email' => $username . '@example.com',
            'username' => $username,
            'password' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'locale_code' => 'en_US',
        ]);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        self::assertInstanceOf(UserInterface::class, $admin);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.admin_user');
        $manager->persist($admin);
        $manager->flush();

        return $admin;
    }
}
