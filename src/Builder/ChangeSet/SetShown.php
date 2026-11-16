<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

/**
 * Switches a translation's "Show in <language>" on or off: off holds it back. See "Held-back translations" in
 * docs/spec/publishing.md
 */
final readonly class SetShown implements Change
{
    public function __construct(
        public string $locale,
        public bool $shown,
    ) {
    }
}
