<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

/**
 * What EntryRewriter rewrote of an entry
 */
final readonly class EntryRewrite
{
    /**
     * @param bool $blockTree whether the block tree in its rows was upgraded
     * @param bool $draft whether its draft was upgraded
     * @param list<NewerVersion> $newer what in its rows, draft and revisions was saved with a newer version than the code's, which is kept as it is
     * @param int $revisions how many of its revisions were upgraded
     */
    public function __construct(
        public bool $blockTree,
        public bool $draft,
        public array $newer,
        public int $revisions,
    ) {
    }
}
