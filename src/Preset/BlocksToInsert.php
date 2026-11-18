<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A preset's blocks made ready to insert into an entry, with what editors are told about them: the blocks of removed or
 * disabled types that were left out, or why the insert is refused. See "What inserting copies" in
 * docs/spec/layouts-and-presets.md
 */
final readonly class BlocksToInsert
{
    /**
     * @param list<StoredBlock> $blocks the top-level blocks to insert, in order, empty when refused
     * @param list<TranslatableMessage> $notices
     * @param bool $refused whether a block was saved with a newer version of its block type than the code's, so nothing is inserted
     */
    public function __construct(
        public array $blocks,
        public array $notices = [],
        public bool $refused = false,
    ) {
    }
}
