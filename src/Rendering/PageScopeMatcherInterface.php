<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

/**
 * Decides which of a slot's placements show on the page by their page scopes, before anything renders, so it also
 * picks a capped slot's winners. See "Page scopes" in docs/spec/slots.md and "Rendering a slot" in
 * docs/spec/rendering.md
 */
interface PageScopeMatcherInterface
{
    /**
     * The placements whose page scope matches the host-page context in the render context, in the order given. An
     * empty scope matches every page the slot appears on. A scope's conditions combine with AND, and a condition of a
     * kind the code no longer has, or whose kind needs a context key the page doesn't provide, keeps its placement out
     *
     * @param list<PlacementData> $placements
     *
     * @return list<PlacementData>
     */
    public function matching(array $placements, RenderContext $context): array;
}
