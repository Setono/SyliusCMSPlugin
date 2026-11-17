<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;

/**
 * One of the placements a slot is asked to show, with whether it shows and, if not, why
 */
final readonly class CandidatePlacement
{
    /**
     * @param SnippetInterface|null $snippet its snippet, when its page scope matches and the snippet is visible to the
     *                                       shopper, so also when it needs context the slot doesn't provide or the cap
     *                                       was reached. Null otherwise
     * @param bool $previewed whether it's the placement a snippet's preview or share link shows, which wins there as if
     *                        it won for shoppers, with the snippet's draft
     */
    public function __construct(
        public PlacementData $placement,
        public PlacementOutcome $outcome,
        public ?SnippetInterface $snippet = null,
        public bool $previewed = false,
    ) {
        if (PlacementOutcome::Wins === $outcome && null === $snippet) {
            throw new \InvalidArgumentException('A placement that wins has its snippet');
        }
    }

    /**
     * Whether the slot shows it. Its snippet may still show nothing, when its blocks are all hidden
     *
     * @phpstan-assert-if-true !null $this->snippet
     */
    public function wins(): bool
    {
        return PlacementOutcome::Wins === $this->outcome;
    }
}
