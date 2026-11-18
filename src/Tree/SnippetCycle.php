<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;

/**
 * A Snippet field in a snippet's tree through which the snippet would show inside itself, with the way back to it
 *
 * @internal
 */
final readonly class SnippetCycle
{
    /**
     * @param FoundReference $reference the Snippet field, in the block that holds it
     * @param list<SnippetInterface> $through the snippets the way back leads through, in order: the one the field shows
     *                                        first, each showing the next, and the last showing the snippet again. Empty
     *                                        when the field shows the snippet itself
     */
    public function __construct(
        public FoundReference $reference,
        public array $through,
    ) {
    }
}
