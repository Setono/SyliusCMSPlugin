<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * An entry whose block tree shows a snippet through a Snippet field: in its published content, which shoppers see, in
 * its working copy, which publishing it would put there, or in both
 *
 * @internal
 */
final readonly class SnippetUse
{
    /**
     * @param bool $published whether its published content shows the snippet
     * @param EntryInterface|null $workingCopy its working copy, when that shows the snippet, or null
     */
    public function __construct(
        public EntryInterface $entry,
        public bool $published,
        public ?EntryInterface $workingCopy,
    ) {
    }
}
