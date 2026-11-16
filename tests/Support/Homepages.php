<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Factory\HomepageFactory;
use Setono\SyliusCMSPlugin\Homepage\HomepageModifier;
use Setono\SyliusCMSPlugin\Model\Homepage;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;

/**
 * For unit tests: changes the channels a page is the homepage of with the shipped row model, as the container does when
 * a project doesn't override it
 */
final class Homepages
{
    /**
     * @param HomepageRepositoryInterface $repository where other pages' homepages are found, which only
     *                                                HomepageModifier::takeOffOtherPages() reads
     */
    public static function modifier(HomepageRepositoryInterface $repository): HomepageModifier
    {
        return new HomepageModifier(self::factory(), $repository);
    }

    public static function factory(): HomepageFactory
    {
        return new HomepageFactory(Homepage::class);
    }
}
