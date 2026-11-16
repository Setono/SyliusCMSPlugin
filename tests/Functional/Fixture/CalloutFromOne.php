<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;

/**
 * Upgrades a Callout block from version 1, where its "message" was called "text"
 */
#[AsUpgrader(of: Callout::class, from: 1)]
final class CalloutFromOne implements UpgraderInterface
{
    public function upgrade(array $data): array
    {
        foreach (['fields', 'translated'] as $map) {
            /** @var array<string, mixed> $values */
            $values = $data[$map];
            if (array_key_exists('text', $values)) {
                $values['message'] = $values['text'];
                unset($values['text']);
            }

            $data[$map] = $values;
        }

        return $data;
    }
}
