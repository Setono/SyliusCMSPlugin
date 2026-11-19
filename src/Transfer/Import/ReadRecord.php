<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * A record of the export as the import read it, which it decides what to do with: an entry, an editor preset or a media
 * item. Media folders aren't decided, as an import never changes a folder the target has (ImportPlanner)
 */
interface ReadRecord
{
    public function kind(): RecordKind;

    public function uuid(): string;

    /**
     * What names it
     */
    public function label(): string;

    /**
     * When it last changed in the source, as the export records it
     */
    public function changedAt(): \DateTimeImmutable;

    /**
     * Whether the export picked it, rather than it coming along as a dependency
     */
    public function isPicked(): bool;
}
