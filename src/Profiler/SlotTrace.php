<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Profiler;

use Setono\SyliusCMSPlugin\Rendering\CandidatePlacement;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * What the profiler saw of one slot rendering, filled in by the traceable decorators as the slot goes through the steps
 * of "Rendering a slot" in docs/spec/rendering.md. A slot without placements stops before it resolves its context,
 * and one whose context can't be resolved before it picks
 *
 * @internal
 */
final class SlotTrace
{
    /**
     * @var array<string, array{object: object, passed: bool}>|null the context it resolved, by key, with whether each
     *                                                               object was passed, such as by the template event,
     *                                                               rather than found by the key's context resolver.
     *                                                               Null when it resolved none
     */
    public ?array $context = null;

    /** The context key it couldn't resolve, which made it print nothing */
    public ?string $unresolvedKey = null;

    /** @var list<CandidatePlacement>|null every one of its placements, in editor order, with its outcome, once it picked */
    public ?array $candidates = null;

    /** What it picked in, which also says why a candidate's snippet isn't visible */
    public ?RenderContext $renderContext = null;

    /** @var list<RecordedFailure> the blocks and snippets that threw in it */
    public array $failures = [];

    /** How long it took to render, in milliseconds, once it's done */
    public ?float $duration = null;

    /**
     * @param string|null $subRequest the route of the sub-request it rendered in, such as Sylius's taxon header's, or
     *                                null in the main request
     */
    public function __construct(
        public readonly string $identifier,
        public readonly ?string $subRequest = null,
    ) {
    }
}
