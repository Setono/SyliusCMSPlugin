<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Format;

use Setono\SyliusCMSPlugin\Publishing\EntrySettings;

/**
 * An export's entries/<uuid>.json: an entry in the entry format, with what travels beside it, its on-save settings and a
 * snippet's placements, and when it last changed. See "entries/<uuid>.json" in docs/spec/transfer.md
 */
final readonly class ExportedEntry
{
    /**
     * @param \DateTimeImmutable $changedAt when its published content or an on-save setting last changed
     * @param list<ExportedPlacement> $placements a snippet's placements, by slot and then in each slot's order. Empty for
     *                                            every other content type
     * @param mixed $entry the entry in the entry format, as EntryNormalizer writes it or CanonicalJson::decode() reads it
     */
    public function __construct(
        public \DateTimeImmutable $changedAt,
        public EntrySettings $settings,
        public array $placements,
        public mixed $entry,
    ) {
    }
}
