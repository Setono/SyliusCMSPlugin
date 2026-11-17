<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Condition\ConditionMatcher;
use Setono\SyliusCMSPlugin\Field\Reference;

/**
 * Matches page scopes as visibility conditions match, since they're in the same stored condition format: a parameter
 * that refers to a record, such as a product condition's products, is hydrated from the records the placements' scopes
 * refer to, which load in one pass for all of them. A condition that throws, such as a project's kind, goes to the
 * failure handler as a block that throws does: in production its placement doesn't show and the others still do
 */
final readonly class PageScopeMatcher implements PageScopeMatcherInterface
{
    public function __construct(
        private ConditionMatcher $conditions,
        private ReferenceLoaderInterface $referenceLoader,
        private FailureHandlerInterface $failures,
    ) {
    }

    public function matching(array $placements, RenderContext $context): array
    {
        $scoped = array_filter($placements, static fn (PlacementData $placement): bool => [] !== $placement->pageScope);
        if ([] === $scoped) {
            return $placements;
        }

        $references = $this->referenceLoader->load($this->references($scoped), $context);

        return array_values(array_filter(
            $placements,
            fn (PlacementData $placement): bool => $this->matches($placement, $context, $references),
        ));
    }

    /**
     * Whether all of the placement's page scope conditions match, as ConditionMatcher::matchesAll() decides, handing a
     * condition that throws to the failure handler
     */
    private function matches(PlacementData $placement, RenderContext $context, ReferenceMap $references): bool
    {
        foreach ($placement->pageScope as $condition) {
            try {
                if (!$this->conditions->matches($condition, $context, $references)) {
                    return false;
                }
            } catch (\Throwable $e) {
                $kind = $condition['kind'] ?? null;
                $this->failures->pageScopeFailed($placement, is_string($kind) ? $kind : '', $e);

                return false;
            }
        }

        return true;
    }

    /**
     * @param array<PlacementData> $placements
     *
     * @return \Generator<Reference>
     */
    private function references(array $placements): \Generator
    {
        foreach ($placements as $placement) {
            yield from $this->conditions->references($placement->pageScope);
        }
    }
}
