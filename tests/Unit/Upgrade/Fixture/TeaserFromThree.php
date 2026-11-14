<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Teaser;
use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;

/**
 * An upgrader from the Teaser block type's own version, 3, as when the version wasn't bumped
 */
#[AsUpgrader(of: Teaser::class, from: 3)]
final class TeaserFromThree implements UpgraderInterface
{
    public function upgrade(array $data): array
    {
        return $data;
    }
}
