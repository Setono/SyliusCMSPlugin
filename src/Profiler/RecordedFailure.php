<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Profiler;

/**
 * A block, or a snippet in a slot, that threw while it rendered, as the profiler shows it
 *
 * @internal
 */
final readonly class RecordedFailure
{
    /**
     * @param string|null $entry the UUID of the entry whose tree holds the block, or of the snippet
     * @param string|null $block the block's key, or null for a snippet
     * @param string|null $blockType the block's block type, or null for a snippet
     * @param string|null $placement the snippet's placement, or null for a block
     */
    public function __construct(
        public ?string $entry,
        public ?string $block,
        public ?string $blockType,
        public ?string $placement,
        public string $message,
    ) {
    }
}
