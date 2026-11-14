<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Teaser;
use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;

/**
 * An upgrader from version 0, which nothing is stored with
 */
#[AsUpgrader(of: Teaser::class, from: 0)]
final class TeaserFromZero implements UpgraderInterface
{
    public function upgrade(array $data): array
    {
        return $data;
    }
}
