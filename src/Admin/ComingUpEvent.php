<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Something that happens to an entry at a time, as the Overview's Coming up lists it: its scheduled publish, or the
 * start or end of its date window. See "Overview" in docs/spec/admin.md
 */
final readonly class ComingUpEvent
{
    /**
     * @param \DateTimeImmutable $at in UTC, as the columns hold it
     */
    public function __construct(
        public ComingUpKind $kind,
        public \DateTimeImmutable $at,
        public EntryInterface $entry,
    ) {
    }
}
