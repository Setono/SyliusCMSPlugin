<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;

/**
 * Upgrades the Article content type, which is at version 2, from version 1
 */
#[AsUpgrader(of: Article::class, from: 1)]
final class ArticleFromOne implements UpgraderInterface
{
    public function upgrade(array $data): array
    {
        return $data;
    }
}
