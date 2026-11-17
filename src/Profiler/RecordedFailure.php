<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Profiler;

/**
 * A block, a snippet in a slot, or a condition of a placement's page scope, that threw while it rendered, as the
 * profiler shows it
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
     * @param string|null $conditionKind the kind of the page scope's condition that threw, or null when the snippet or
     *                                   the block did
     */
    public function __construct(
        public ?string $entry,
        public ?string $block,
        public ?string $blockType,
        public ?string $placement,
        public string $message,
        public ?string $conditionKind = null,
    ) {
    }
}
