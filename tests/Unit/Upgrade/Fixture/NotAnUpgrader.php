<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Teaser;

/**
 * Carries #[AsUpgrader] without implementing UpgraderInterface
 */
#[AsUpgrader(of: Teaser::class, from: 1)]
final class NotAnUpgrader
{
}
