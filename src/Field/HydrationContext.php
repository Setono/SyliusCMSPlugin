<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * What a field type handler gets when it hydrates a value: the render context, and the records the references of the
 * tree it renders resolve to, loaded in one pass before rendering. A handler hydrates a reference the map doesn't hold
 * as missing: it's broken, or the shopper may not see it
 */
final readonly class HydrationContext
{
    public function __construct(
        public RenderContext $renderContext,
        public ReferenceMap $references = new ReferenceMap(),
    ) {
    }
}
