<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Field\Reference;

/**
 * Loads the records a block tree's references resolve to, before the tree renders. A project can decorate it to cache
 * references, invalidating on StorefrontChangedInterface events. See "Rendering services" in docs/spec/extending.md
 */
interface ReferenceLoaderInterface
{
    /**
     * @param iterable<Reference> $references Loads them with one query per type
     */
    public function load(iterable $references, RenderContext $context): ReferenceMap;
}
