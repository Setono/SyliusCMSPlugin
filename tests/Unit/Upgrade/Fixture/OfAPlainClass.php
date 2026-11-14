<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;

/**
 * An upgrader of a class that is no block type, layout or content type
 */
#[AsUpgrader(of: \stdClass::class, from: 1)]
final class OfAPlainClass implements UpgraderInterface
{
    public function upgrade(array $data): array
    {
        return $data;
    }
}
