<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\StoreLocation;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\StoreLocationTranslation;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helpers for the test application's own content types: store locations, which have a URL under "stores", a shared
 * field (the city) and a translatable one (the directions), and announcements, which editors order by position
 */
trait StoreLocations
{
    abstract protected static function getContainer(): ContainerInterface;

    /**
     * Creates a store location in en_US through its resource's own factory. Like any new entry, it's in every channel
     */
    protected static function createStoreLocation(string $title, string $slug, ?string $city = null, ?string $directions = null, bool $published = false): StoreLocation
    {
        /** @var FactoryInterface<StoreLocation> $factory */
        $factory = self::getContainer()->get('app.factory.store_location');

        $storeLocation = $factory->createNew();
        $storeLocation->setCurrentLocale('en_US');
        $storeLocation->setFallbackLocale('en_US');
        $storeLocation->setTitle($title);
        $storeLocation->setSlug($slug);
        $storeLocation->setCity($city);

        $translation = $storeLocation->getTranslation('en_US');
        self::assertInstanceOf(StoreLocationTranslation::class, $translation);
        $translation->setDirections($directions);

        if ($published) {
            $storeLocation->publish(new \DateTimeImmutable());
        }

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('app.manager.store_location');
        $manager->persist($storeLocation);
        $manager->flush();

        return $storeLocation;
    }

    /**
     * Creates an announcement in en_US through its resource's own factory, with its required subtitle
     */
    protected static function createAnnouncement(string $title, bool $published = false): Announcement
    {
        /** @var FactoryInterface<Announcement> $factory */
        $factory = self::getContainer()->get('app.factory.announcement');

        $announcement = $factory->createNew();
        $announcement->setCurrentLocale('en_US');
        $announcement->setFallbackLocale('en_US');
        $announcement->setTitle($title);
        $announcement->setSubtitle(sprintf('About %s', $title));

        if ($published) {
            $announcement->publish(new \DateTimeImmutable());
        }

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('app.manager.announcement');
        $manager->persist($announcement);
        $manager->flush();

        return $announcement;
    }
}
