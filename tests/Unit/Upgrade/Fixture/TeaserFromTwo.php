<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Teaser;
use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;

/**
 * Upgrades the Teaser block type, which is at version 3, from version 2
 */
#[AsUpgrader(of: Teaser::class, from: 2)]
final class TeaserFromTwo implements UpgraderInterface
{
    public function upgrade(array $data): array
    {
        return $data;
    }
}
