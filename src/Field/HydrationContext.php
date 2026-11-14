<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * What a field type handler gets when it hydrates a value
 */
final readonly class HydrationContext
{
    public function __construct(public RenderContext $renderContext)
    {
    }
}
