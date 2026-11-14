<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

/**
 * Registers an upgrader: one step of a block type's, layout's or content type's upgrade, from version $from to
 * $from + 1. The class implements UpgraderInterface. See "Upgraders" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsUpgrader
{
    /**
     * @param class-string $of a block type, layout or content type class
     * @param int $from the version it upgrades from, to $from + 1
     */
    public function __construct(
        public string $of,
        public int $from,
    ) {
    }
}
