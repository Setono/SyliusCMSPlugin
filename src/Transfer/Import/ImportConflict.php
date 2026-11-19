<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * A conflict the plan lists: the import refuses while there's one, unless overwriting resolves it and was asked for.
 * See "Conflicts" in docs/spec/transfer.md
 */
final readonly class ImportConflict
{
    /**
     * @param string $uuid the record it's about
     * @param TranslatableInterface $message what's in the way, naming the record, in the messages domain
     */
    public function __construct(
        public ConflictKind $kind,
        public string $uuid,
        public TranslatableInterface $message,
    ) {
    }
}
