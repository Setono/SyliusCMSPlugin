<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Teaser;
use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;

/**
 * A second upgrader of the Teaser block type from version 2
 */
#[AsUpgrader(of: Teaser::class, from: 2)]
final class DuplicateTeaserFromTwo implements UpgraderInterface
{
    public function upgrade(array $data): array
    {
        return $data;
    }
}
