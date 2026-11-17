<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Condition\ConditionMatcher;
use Setono\SyliusCMSPlugin\Field\Reference;

/**
 * Matches page scopes as visibility conditions match, since they're in the same stored condition format: a parameter
 * that refers to a record, such as a product condition's products, is hydrated from the records the placements' scopes
 * refer to, which load in one pass for all of them
 */
final readonly class PageScopeMatcher implements PageScopeMatcherInterface
{
    public function __construct(
        private ConditionMatcher $conditions,
        private ReferenceLoaderInterface $referenceLoader,
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
            fn (PlacementData $placement): bool => $this->conditions->matchesAll($placement->pageScope, $context, $references),
        ));
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
