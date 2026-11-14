<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

/**
 * Stored data brought up to date with the code, and what in it was saved with a newer version than the code's,
 * which is kept as it is
 */
final readonly class Upgraded
{
    /**
     * @param array<array-key, mixed> $data the upgraded data, in the shape CanonicalJson::decode() gives
     * @param list<NewerVersion> $newer
     */
    public function __construct(
        public array $data,
        public array $newer = [],
    ) {
    }
}
