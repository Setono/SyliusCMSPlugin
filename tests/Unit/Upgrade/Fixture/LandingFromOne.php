<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;

/**
 * Upgrades the Landing page layout, which is at version 2, from version 1
 */
#[AsUpgrader(of: Landing::class, from: 1)]
final class LandingFromOne implements UpgraderInterface
{
    public function upgrade(array $data): array
    {
        return $data;
    }
}
