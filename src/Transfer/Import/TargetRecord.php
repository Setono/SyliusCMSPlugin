<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * What the target holds of a record of the export, matched by UUID
 */
final readonly class TargetRecord
{
    /**
     * @param \DateTimeImmutable $changedAt when its published content, an on-save setting or a placement last changed in
     *                                      the target
     * @param bool $identical whether it's identical to the export's: an entry's published content, or its rows if it was
     *                        never published, its on-save settings and a snippet's placements, or a preset's content
     * @param bool $hasDraft whether an entry has a draft
     * @param bool $hasScheduledPublish whether an entry has a scheduled publish
     */
    public function __construct(
        public \DateTimeImmutable $changedAt,
        public bool $identical,
        public bool $hasDraft = false,
        public bool $hasScheduledPublish = false,
    ) {
    }
}
