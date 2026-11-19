<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

/**
 * What PresetRewriter rewrote of an editor preset
 */
final readonly class PresetRewrite
{
    /**
     * @param bool $rewritten whether its blocks or its starting point's layout were upgraded
     * @param list<NewerVersion> $newer what in it was saved with a newer version than the code's, which is kept as it is
     */
    public function __construct(
        public bool $rewritten,
        public array $newer,
    ) {
    }
}
