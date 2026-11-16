<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Factory\HomepageFactory;
use Setono\SyliusCMSPlugin\Model\Homepage;

/**
 * For unit tests: creates a page's homepage rows with the shipped row model, as the container does when a project
 * doesn't override it
 */
final class Homepages
{
    public static function factory(): HomepageFactory
    {
        return new HomepageFactory(Homepage::class);
    }
}
